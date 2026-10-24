#!/usr/bin/env python3
"""Build an isolated-rehearsal kit; accepted baseline plus explicit presentation changes."""
import argparse
from pathlib import Path
from importlib.machinery import SourceFileLoader
b=SourceFileLoader('stage6_build', str(Path(__file__).with_name('build-stage6.py'))).load_module()
def build(output):
    app=b.sources(b.SOURCE)
    for name in ['ft2.php','config.sample.php','css/ft.css','js/filethingie.js']:
        app['index.php' if name=='ft2.php' else name]=(b.ROOT/name).read_bytes()
    manifest={'baseline_commit':b.SOURCE,'status':'rehearsal; production cutover not verified',
              'files':{p:b.digest(v) for p,v in sorted(app.items())}}
    kit={'app/'+p:v for p,v in app.items()}
    kit['rehearsal-manifest.json']=b.encoded(manifest)
    for name in ['sakura-rehearsal.php','sakura-backup.php','sakura-inventory.php']:
        kit[name]=(b.ROOT/'tools'/name).read_bytes()
    kit['REHEARSAL.md']=(b.ROOT/'docs/STAGE6_REHEARSAL.md').read_bytes()
    output.parent.mkdir(parents=True,exist_ok=True)
    b.archive(output,kit)
    output.with_suffix('.sha256').write_text(b.digest(output.read_bytes())+'  '+output.name+'\n')
    print(b.digest(output.read_bytes()))
if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('output',type=Path);build(parser.parse_args().output)
