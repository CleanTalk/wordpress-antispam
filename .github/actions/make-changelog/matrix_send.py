#!/usr/bin/env python3
from __future__ import annotations

import json
import sys
import urllib.parse
import urllib.request
import uuid


def main() -> int:
    if len(sys.argv) != 6:
        raise SystemExit("usage: matrix_send.py <server> <room> <token> <body> <formatted>")

    server, room, token, body, formatted = sys.argv[1:6]
    if not server or not room or not token:
        return 0

    server = server.rstrip('/')
    room_q = urllib.parse.quote(room, safe='')
    txn = urllib.parse.quote(uuid.uuid4().hex, safe='')
    url = f"{server}/_matrix/client/v3/rooms/{room_q}/send/m.room.message/{txn}"

    payload = {"msgtype": "m.text", "body": body}
    if formatted:
        payload["format"] = "org.matrix.custom.html"
        payload["formatted_body"] = formatted

    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode(),
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {token}",
        },
        method="PUT",
    )

    try:
        with urllib.request.urlopen(req, timeout=30) as response:
            response.read()
    except urllib.error.HTTPError as e:
        detail = e.read().decode("utf-8", errors="replace")
        raise SystemExit(f"Matrix send failed with HTTP {e.code}: {detail}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
