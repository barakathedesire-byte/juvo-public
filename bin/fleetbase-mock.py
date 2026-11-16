import http.server
import json
import os

LOG = "/tmp/opencode/fleetbase_mock.log"


class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        raw = json.dumps({"status": "ok", "service": "fleetbase-mock"}).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length).decode("utf-8", "replace")
        with open(LOG, "a") as fh:
            fh.write("--- %s %s\n" % (self.command, self.path))
            fh.write("Authorization: %s\n" % self.headers.get("Authorization"))
            fh.write("Content-Type: %s\n" % self.headers.get("Content-Type"))
            fh.write(body + "\n")
        payload = {
            "id": "order_mock123",
            "tracking_number": "TRK-MOCK-0001",
            "status": "created",
            "data": {
                "id": "order_mock123",
                "tracking_number": "TRK-MOCK-0001",
                "status": "created",
            },
        }
        raw = json.dumps(payload).encode()
        self.send_response(201)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    try:
        os.remove(LOG)
    except FileNotFoundError:
        pass
    http.server.HTTPServer(("127.0.0.1", 9099), Handler).serve_forever()
