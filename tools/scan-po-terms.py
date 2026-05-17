from pathlib import Path

"""Scan PO entries for selected translation terms.

Usage:
    python tools/scan-po-terms.py
"""

po = Path('languages/volunteer-exchange-platform-da_DK.po').read_text(encoding='utf-8')
entries = []
msgid = None
for line in po.splitlines():
    if line.startswith('msgid "'):
        msgid = line[7:-1]
    elif line.startswith('msgstr "') and msgid is not None:
        msgstr = line[8:-1]
        entries.append((msgid, msgstr))
        msgid = None

keywords = [
    'event', 'Event', 'begivenhed', 'Begivenhed',
    'participant', 'Participant', 'deltager', 'Deltager',
    'agreement', 'aftale', 'tag', 'type', 'organisation', 'organization',
]
for msgid, msgstr in entries:
    text = msgid + ' || ' + msgstr
    if any(keyword in text for keyword in keywords):
        print(f'{msgid} => {msgstr}')
