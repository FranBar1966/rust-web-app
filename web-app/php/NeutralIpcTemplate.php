<?php
/**
 * Neutral PHP IPC Library
 *
 * @version 1.2.0
 * @description IPC client for Neutral TS.
 */

include 'NeutralIpcConfig.php';

class NeutralIpcRecord
{
    # ============================================
    # Neutral IPC record version 0 (draft version)
    # ============================================
    #
    # HEADER:
    #
    # \x00              # reserved
    # \x00              # control (action/status) (10 = parse template)
    # \x00              # content-format 1 (10 = JSON, 20 = file path, 30 = plaintext, 40 = binary)
    # \x00\x00\x00\x00  # content-length 1 big endian byte order
    # \x00              # content-format 2 (10 = JSON, 20 = file path, 30 = plaintext, 40 = binary)
    # \x00\x00\x00\x00  # content-length 2 big endian byte order (can be zero)
    #
    # All text utf8

    const RESERVED            = 0;
    const HEADER_LEN          = 12;
    const CTRL_PARSE_TEMPLATE = 10;
    const CTRL_STATUS_OK      = 0;
    const CTRL_STATUS_KO      = 1;
    const CONTENT_JSON        = 10;
    const CONTENT_PATH        = 20;
    const CONTENT_TEXT        = 30;
    const CONTENT_BIN         = 40;

    public static function decodeHeader($header)
    {
        $header_array = unpack("creserved/ccontrol/cformat-1/Nlength-1/cformat-2/Nlength-2", $header);

        return [
            'reserved' => $header_array['reserved'],
            'control'  => $header_array['control'],
            'format-1' => $header_array['format-1'],
            'length-1' => $header_array['length-1'],
            'format-2' => $header_array['format-2'],
            'length-2' => $header_array['length-2'],
        ];
    }

    public static function encodeHeader($control, $format1, $length1, $format2, $length2)
    {
        return pack("CCCNCN",
            self::RESERVED,
            $control,
            $format1,
            $length1,
            $format2,
            $length2
        );
    }

    public static function encodeRecord($control, $format1, $content1, $format2, $content2)
    {
        $length1 = strlen($content1);
        $length2 = strlen($content2);
        $header  = self::encodeHeader($control, $format1, $length1, $format2, $length2);
        $record  = $header . $content1 . $content2;

        return $record;
    }

    public static function decodeRecord($header, $content1, $content2)
    {
        return [
            'reserved'  => ord($header[0]),
            'control'   => ord($header[1]),
            'format-1'  => ord($header[2]),
            'content-1' => $content1,
            'format-2'  => ord($header[7]),
            'content-2' => $content2,
        ];
    }
}

class NeutralIpcClient
{
    protected $control;
    protected $format1;
    protected $content1;
    protected $format2;
    protected $content2;
    protected $result;
    protected $stream;

    public function __construct(string $control, string $format1, string $content1, string $format2, string $content2)
    {
        $this->control  = $control;
        $this->format1  = $format1;
        $this->content1 = $content1;
        $this->format2  = $format2;
        $this->content2 = $content2;
        $this->result   = [];
    }

    public function start()
    {
        $context = stream_context_create([
            'socket' => [
                'timeout' => NeutralIpcConfig::TIMEOUT,
            ],
        ]);

        $this->stream = stream_socket_client(
            "tcp://" . NeutralIpcConfig::HOST . ":" . NeutralIpcConfig::PORT,
            $errno,
            $errstr,
            NeutralIpcConfig::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->stream === false) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        $request = NeutralIpcRecord::encodeRecord(
            $this->control, $this->format1, $this->content1, $this->format2, $this->content2
        );
        fwrite($this->stream, $request);

        $response_header = fread($this->stream, NeutralIpcRecord::HEADER_LEN);
        if (strlen($response_header) !== NeutralIpcRecord::HEADER_LEN) {
            fclose($this->stream);
            throw new Exception("Incomplete header received");
        }

        $response = NeutralIpcRecord::decodeHeader($response_header);

        $readContent = function($stream, $length) {
            $content = '';
            $bufferSize = NeutralIpcConfig::BUFFER_SIZE;
            while ($length > 0) {
                $chunkSize = min($bufferSize, $length);
                $chunk = fread($stream, $chunkSize);
                if ($chunk === false) {
                    throw new Exception("Error reading from stream");
                }
                $content .= $chunk;
                $length -= strlen($chunk);
            }
            return $content;
        };

        $content1 = $readContent($this->stream, $response['length-1']);
        if (strlen($content1) !== $response['length-1']) {
            throw new Exception("Incomplete content-1 received");
        }

        $content2 = $readContent($this->stream, $response['length-2']);
        if (strlen($content2) !== $response['length-2']) {
            throw new Exception("Incomplete content-2 received");
        }

        $this->result = NeutralIpcRecord::decodeRecord($response_header, $content1, $content2);

        return $this->result;
    }
}

class NeutralIpcTemplate
{
    protected $template;
    protected $tpltype; // template type CONTENT_PATH (file) or CONTENT_TEXT (raw source)
    protected $schema;  // array schema or string json schema
    protected $result = [];

    public function __construct(string $template, mixed $schema, int $tpltype = NeutralIpcRecord::CONTENT_PATH)
    {
        $this->template = $template;
        $this->tpltype  = $tpltype;

        if (is_string($schema)) {
            $this->schema = $schema;
        } else {
            $this->schema = json_encode($schema);
        }
    }

    public function render()
    {
        $record = new NeutralIpcClient(
            NeutralIpcRecord::CTRL_PARSE_TEMPLATE,
            NeutralIpcRecord::CONTENT_JSON,
            $this->schema,
            $this->tpltype,
            $this->template
        );

        $result = $record->start();

        $this->result = [
            'status'  => $result['control'],
            'result'  => json_decode($result['content-1'], true),
            'content' => $result['content-2'],
        ];

        return $this->result['content'];
    }

    public function set_path(string $path)
    {
        $this->tpltype  = NeutralIpcRecord::CONTENT_PATH;
        $this->template = $path;
    }

    public function set_source(string $source)
    {
        $this->tpltype  = NeutralIpcRecord::CONTENT_TEXT;
        $this->template = $source;
    }

    public function merge_schema(mixed $schema)
    {
        $new_schema = [];

        if (is_string($schema)) {
            $new_schema = array_replace_recursive(json_decode($this->schema, true), json_decode($schema, true));
        } else {
            $new_schema = array_replace_recursive(json_decode($this->schema, true), $schema);
        }

        $this->schema = json_encode($new_schema);
    }

    public function has_error()
    {
        if ($this->result['status'] != 0 || $this->result['result']['has_error']) {
            return true;
        } else {
            return false;
        }
    }

    public function get_status_code()
    {
        return $this->result['result']['status_code'] ?? null;
    }

    public function get_status_text()
    {
        return $this->result['result']['status_text'] ?? null;
    }

    public function get_status_param()
    {
        return $this->result['result']['status_param'] ?? null;
    }

    public function get_result()
    {
        return $this->result['result'] ?? null;
    }
}
