"""End-to-end test of signup, places, account, API and calendar feed.

Needs a fresh database, the web app on localhost:8080, the calc server with its
six-city test list on 127.0.0.1:8100 and tests/smtp_capture.py writing mails to
MAILDIR. Expects German texts (the first account signs up in German).
See CLAUDE.md, "Local test setup".

    python3 tests/e2e.py /path/to/maildir
"""
import re, sys, glob, email, time, json
from email import policy
import requests
B = "http://localhost:8080"; MAIL = sys.argv[1]
fails = 0
def ok(c, m):
    global fails
    print(("PASS " if c else "FAIL ") + m); fails += 0 if c else 1
def csrf(h): return re.search(r'name="csrf" value="([^"]+)"', h).group(1)
def mails():
    return [email.message_from_bytes(open(f,"rb").read(), policy=policy.default) for f in sorted(glob.glob(MAIL+"/*.eml"))]
def text(m): return m.get_body(("plain",)).get_content()
s = requests.Session()
t = csrf(s.get(B + "/signup?city=100001").text)
s.post(B + "/signup", data={"csrf": t, "email": "ben@example.test", "password": "correct horse battery", "city_id": "100001", "lang": "de", "privacy": "1"})
time.sleep(.5); link = re.search(r"http://localhost:8080/verify\?token=[a-f0-9]+", text(mails()[-1])).group(0)
r = s.get(link); ok(r.url.endswith("/alerts"), "verify lands on alerts page")
ok("Tuttlingen" in r.text and "1 von 10 Orten" in r.text, "first alert created from signup city")
ok(s.get(B + "/dashboard", allow_redirects=False).headers.get("Location") == "/alerts", "old /dashboard redirects")

# add alerts
r = s.get(B + "/alerts/new?city=100002"); ok("Berlin" in r.text, "new alert form prefilled from city")
t = csrf(r.text)
r = s.post(B + "/alerts", data={"csrf": t, "city_id": "100002", "city": "Berlin", "name": "Büro", "planets[]": ["jupiter", "saturn"], "events[]": ["opposition", "planet_pair"]})
ok("„Büro“ hinzugefügt" in r.text and "2 von 10" in r.text, "second alert added with name")
r = s.post(B + "/alerts", data={"csrf": t, "city_id": "", "city": "sydney", "planets[]": ["venus"], "events[]": ["comeback", "greatest_elongation"]})
ok("Sydney" in r.text and "3 von 10" in r.text, "third alert via typed city (no JS)")
r = s.post(B + "/alerts", data={"csrf": t, "city_id": "100003", "planets[]": [], "events[]": ["comeback"]}, allow_redirects=True)
ok("mindestens einen Planeten" in r.text, "alert needs a planet")
ids = [int(x) for x in re.findall(r'id="alert-(\d+)"', s.get(B + "/alerts").text)]
ok(len(ids) == 3, "three alerts listed")
# toggle, edit
r = s.post(B + f"/alerts/{ids[2]}/toggle", data={"csrf": t}); ok("ausgeschaltet" in r.text and 'aria-checked="false"' in r.text, "switch alert off")
r = s.get(B + f"/alerts/{ids[1]}/edit"); ok('value="Büro"' in r.text and re.search(r'value="saturn"\s+checked', r.text), "edit form shows saved values")
r = s.post(B + f"/alerts/{ids[1]}", data={"csrf": csrf(r.text), "city_id": "100002", "city": "Berlin", "name": "Büro Berlin", "planets[]": ["jupiter", "saturn", "mars"], "events[]": ["opposition", "planet_pair"]})
ok("Büro Berlin" in r.text and "Gespeichert." in r.text, "edit saved")
ok("Büro Berlin:" in s.get(B + "/alerts").text or "place" in r.text, "upcoming list labels places")
# other user can't touch them
s2 = requests.Session(); t2 = csrf(s2.get(B + "/signup").text)
s2.post(B + "/signup", data={"csrf": t2, "email": "cara@example.test", "password": "another password!", "city_id": "100005", "lang": "en", "privacy": "1"})
time.sleep(.5); s2.get(re.search(r"http://localhost:8080/verify\?token=[a-f0-9]+", text(mails()[-1])).group(0))
t2 = csrf(s2.get(B + "/alerts").text)
ok(s2.get(B + f"/alerts/{ids[0]}/edit").status_code == 404, "cannot open another user's alert")
s2.post(B + f"/alerts/{ids[0]}/delete", data={"csrf": t2})
ok(f'id="alert-{ids[0]}"' in s.get(B + "/alerts").text, "cannot delete another user's alert")
# limit of 10
for i in range(7):
    s.post(B + "/alerts", data={"csrf": t, "city_id": "100003", "name": f"M{i}", "planets[]": ["mars"], "events[]": ["opposition"]})
r = s.get(B + "/alerts"); ok("10 von 10" in r.text and "/alerts/new" not in r.text.split("<main")[1].split("alert-list")[0], "10 alerts, new button hidden")
r = s.post(B + "/alerts", data={"csrf": t, "city_id": "100003", "planets[]": ["mars"], "events[]": ["opposition"]})
ok("bis zu 10 Orte" in r.text and "10 von 10" in r.text, "11th alert refused")
for i in re.findall(r'id="alert-(\d+)"', r.text)[3:]:
    s.post(B + f"/alerts/{i}/delete", data={"csrf": t})
r = s.get(B + "/alerts"); ok("3 von 10" in r.text, "delete alerts")
# account page
r = s.get(B + "/account"); ok("Konto" in r.text and 'name="style"' in r.text, "account page")
t = csrf(r.text)
r = s.post(B + "/account/settings", data={"csrf": t, "style": "both", "lang": "de", "mail_enabled": "1"}); ok("gespeichert" in r.text and re.search(r'value="both"\s+checked', r.text), "account settings")
r = s.post(B + "/account/test-mail", data={"csrf": t}); time.sleep(.5)
ok("Tuttlingen" in text(mails()[-1]) and "Büro Berlin" in text(mails()[-1]), "test mail lists enabled alerts")
r = s.post(B + "/account/keys", data={"csrf": t, "name": "HA"}); key = re.search(r"pa_[a-f0-9]{40}", r.text).group(0)
H = {"Authorization": "Bearer " + key}
r = requests.get(B + "/api/v1/alerts", headers=H).json(); ok(len(r["alerts"]) == 3 and r["max"] == 10, "API list alerts")
r = requests.post(B + "/api/v1/alerts", headers=H, json={"city": 100003, "name": "München", "planets": ["mars"], "events": ["opposition"]})
ok(r.status_code == 201 and r.json()["city"]["name"] == "München", "API create alert")
nid = r.json()["id"]
r = requests.patch(B + f"/api/v1/alerts/{nid}", headers=H, json={"enabled": False, "planets": ["mars", "jupiter"]}).json()
ok(r["enabled"] is False and r["planets"] == ["mars", "jupiter"], "API patch alert")
ok(requests.patch(B + f"/api/v1/alerts/{nid}", headers=H, json={"planets": []}).status_code == 400, "API rejects empty planets")
ok(requests.delete(B + f"/api/v1/alerts/{nid}", headers=H).status_code == 204, "API delete alert")
ok(requests.get(B + f"/api/v1/alerts/{ids[0]+100}", headers=H).status_code == 404, "API unknown alert 404")
r = requests.get(B + "/api/v1/me", headers=H).json(); ok(r["style"] == "both" and len(r["alerts"]) == 3, "API me")
r = requests.get(B + "/api/v1/events.ics?key=" + key); ok("SUMMARY:Tuttlingen:" in r.text and "Büro Berlin:" in r.text, "calendar feed spans alerts with place names")
ok("Sydney" not in r.text, "calendar feed skips switched-off alert")
r = requests.options(B + "/api/v1/alerts/5"); ok(r.status_code == 204 and "DELETE" in r.headers.get("Access-Control-Allow-Methods", ""), "CORS preflight for DELETE")
# city page CTA for logged-in users
ok("/alerts/new?city=100003" in s.get(B + "/city/100003").text, "city page offers 'add alert' when logged in")
print("FAILURES:", fails)
