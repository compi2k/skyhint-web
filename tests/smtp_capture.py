"""Tiny SMTP server for local tests: saves every mail as a .eml file.

    pip install aiosmtpd
    python3 tests/smtp_capture.py /path/to/maildir   # listens on 127.0.0.1:1025
"""
import asyncio, sys, time, os
from aiosmtpd.controller import Controller
OUT = sys.argv[1]
class H:
    async def handle_DATA(self, server, session, envelope):
        name = os.path.join(OUT, f"{time.time():.6f}.eml")
        open(name, "wb").write(envelope.original_content)
        return "250 OK"
c = Controller(H(), hostname="127.0.0.1", port=1025); c.start()
print("smtp ready", flush=True)
while True: time.sleep(3600)
