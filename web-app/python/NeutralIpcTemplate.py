"""
Neutral Python IPC Library

@version: 1.2.0
@description: IPC client for Neutral TS.
"""

import socket
import struct
import json
from NeutralIpcConfig import NeutralIpcConfig

class NeutralIpcRecord:
    """ Neutral IPC record """

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

    RESERVED = 0
    HEADER_LEN = 12
    CTRL_PARSE_TEMPLATE = 10
    CTRL_STATUS_OK = 0
    CTRL_STATUS_KO = 1
    CONTENT_JSON = 10
    CONTENT_PATH = 20
    CONTENT_TEXT = 30
    CONTENT_BIN = 40
    EMPTY = b''

    @staticmethod
    def decode_header(record_header):
        reserved, control, format1, length1, format2, length2 = struct.unpack(
            '!BBBIBI', record_header
        )
        return {
            'reserved': reserved,
            'control': control,
            'format-1': format1,
            'length-1': length1,
            'format-2': format2,
            'length-2': length2,
        }

    @staticmethod
    def encode_header(control, format1, length1, format2, length2):
        return struct.pack('!BBBIBI',
            NeutralIpcRecord.RESERVED,
            int(control),
            int(format1),
            int(length1),
            int(format2),
            int(length2)
        )

    @staticmethod
    def encode_record(control, format1, content1, format2, content2):
        length1 = len(content1.encode('utf-8'))
        length2 = len(content2.encode('utf-8'))
        header = NeutralIpcRecord.encode_header(control, format1, length1, format2, length2)
        return header + content1.encode('utf-8') + content2.encode('utf-8')

    @staticmethod
    def decode_record(header, content1, content2):
        record = {
            "reserved": NeutralIpcRecord.RESERVED,
            "control": header[1],
            'format-1': header[2],
            'content-1': content1,
            'format-2': header[7],
            'content-2': content2,
        }
        return record


class NeutralIpcClient:
    """ Neutral IPC client """

    def __init__(self, control, format1, content1, format2, content2):
        self.control = control
        self.format1 = format1
        self.content1 = content1
        self.format2 = format2
        self.content2 = content2
        self.result = {}

    def start(self):
        with socket.create_connection((NeutralIpcConfig.HOST, NeutralIpcConfig.PORT), NeutralIpcConfig.TIMEOUT) as conn:
            request = NeutralIpcRecord.encode_record(
                self.control, self.format1, self.content1, self.format2, self.content2
            )
            conn.sendall(request)

            response_header = conn.recv(NeutralIpcRecord.HEADER_LEN)
            if len(response_header) != NeutralIpcRecord.HEADER_LEN:
                raise ValueError("Incomplete header received")

            response = NeutralIpcRecord.decode_header(response_header)

            content1 = self._read_content(conn, response['length-1'])
            content2 = self._read_content(conn, response['length-2'])

            self.result = NeutralIpcRecord.decode_record(response_header, content1, content2)

            return self.result

    def _read_content(self, conn, length):
        chunks = []
        buffer_size = NeutralIpcConfig.BUFFER_SIZE

        while length > 0:
            chunk = conn.recv(min(buffer_size, length))
            if not chunk:
                raise ValueError("Error reading from stream")
            chunks.append(chunk)
            length -= len(chunk)

        return b''.join(chunks).decode('utf-8')

class NeutralIpcTemplate:
    """ Neutral IPC Template """

    def __init__(self, template, schema, tpl_type=NeutralIpcRecord.CONTENT_PATH):
        self.template = template
        self.tpl_type = tpl_type
        self.schema = json.dumps(schema) if not isinstance(schema, str) else schema
        self.result = {}

    def render(self):
        record = NeutralIpcClient(
            NeutralIpcRecord.CTRL_PARSE_TEMPLATE,
            NeutralIpcRecord.CONTENT_JSON,
            self.schema,
            self.tpl_type,
            self.template
        )
        result = record.start()
        self.result = {
            'status': result['control'],
            'result': json.loads(result['content-1']),
            'content': result['content-2'],
        }

        return self.result['content']

    def set_path(self, path):
        self.tpl_type = NeutralIpcRecord.CONTENT_PATH
        self.template = path

    def set_source(self, source):
        self.tpl_type = NeutralIpcRecord.CONTENT_TEXT
        self.template = source

    def merge_schema(self, schema):
        current_schema = json.loads(self.schema)
        new_schema = json.loads(schema) if isinstance(schema, str) else schema
        self.schema = json.dumps(deep_merge(current_schema, new_schema))

    def has_error(self):
        return self.result.get('status') != 0 or self.result['result'].get('has_error', False)

    def get_status_code(self):
        return self.result['result'].get('status_code')

    def get_status_text(self):
        return self.result['result'].get('status_text')

    def get_status_param(self):
        return self.result['result'].get('status_param')

    def get_result(self):
        return self.result.get('result')

def deep_merge(dict1, dict2):
    merged = dict1.copy()

    for key, value in dict2.items():
        if key in merged and isinstance(merged[key], dict) and isinstance(value, dict):
            merged[key] = deep_merge(merged[key], value)
        else:
            merged[key] = value

    return merged
