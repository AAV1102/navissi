import json, os, time, urllib.request
from pathlib import Path
BASE=Path(__file__).resolve().parent.parent
CFG=BASE/'monitor_sync.json'
def enviar():
    c=json.loads(CFG.read_text(encoding='utf-8')); endpoint=c['servidor'].rstrip('/')+'/api_monitor_sync.php'
    for f in sorted((Path(c['carpeta_datos'])).glob('**/*.json')):
        try:
            payload=f.read_bytes(); req=urllib.request.Request(endpoint,payload,headers={'Content-Type':'application/json','X-NAVISSI-TOKEN':c['token']},method='POST')
            with urllib.request.urlopen(req,timeout=60) as r: print(r.read().decode())
            f.rename(f.with_suffix('.enviado.json'))
        except Exception as e: print('sync:',e)
if __name__=='__main__':
    while True: enviar(); time.sleep(int(json.loads(CFG.read_text()) .get('intervalo',300)))
