#!/usr/bin/env python3
"""Prepare short-lived, private credentials for the isolated Sakura UI test."""
import argparse
import datetime
import getpass
import json
import os
from pathlib import Path
import re
import socket
import sys
import tempfile
from urllib.parse import urlsplit


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('url', help='HTTPS URL of the ft-stage5 preview index.php')
    args = parser.parse_args()
    url = urlsplit(args.url)
    if (url.scheme != 'https' or not url.hostname or url.username or url.password
            or url.query or url.fragment
            or not re.search(r'/ft-stage5-\d{8}-\d{6}-[0-9a-f]{8}/index\.php$', url.path)):
        parser.error('Only an isolated HTTPS ft-stage5 preview URL is accepted.')
    if not sys.stdin.isatty():
        parser.error('Run in an interactive terminal; passwords must not be echoed.')
    print('入力先のUbuntu:', socket.gethostname())
    print('検証対象:', args.url)
    print('パスワードは画面に表示されません。チャットへ送る必要はありません。')
    basic_user = input('サイトのBasic認証ユーザー名: ').strip()
    basic_password = getpass.getpass('サイトのBasic認証パスワード: ')
    app_password = getpass.getpass('検証版 stage5 のパスワード: ')
    if not all([basic_user, basic_password, app_password]):
        parser.error('All three values are required; nothing was saved.')
    now = datetime.datetime.now(datetime.timezone.utc)
    record = {
        'purpose': 'filethingie-sakura-preview',
        'url': args.url,
        'created_at': now.isoformat(),
        'expires_at': (now + datetime.timedelta(hours=2)).isoformat(),
        'basic_user': basic_user,
        'basic_password': basic_password,
        'app_user': 'stage5',
        'app_password': app_password,
    }
    directory = Path(tempfile.mkdtemp(prefix='ft-sakura-auth-'))
    target = directory / 'credentials.json'
    try:
        fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'w', encoding='utf-8') as stream:
            json.dump(record, stream, ensure_ascii=False)
            stream.write('\n')
    except BaseException:
        target.unlink(missing_ok=True)
        directory.rmdir()
        raise
    print('準備完了:', target)
    print('有効期間は2時間です。認証ファイルはテスト終了後に削除します。')
    print('チャットには「準備完了」と上記ファイルのパスだけをお知らせください。')


if __name__ == '__main__':
    main()
