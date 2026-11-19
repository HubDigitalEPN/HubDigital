#!/usr/bin/env python3
"""Perfil persistente para DESA de 1 GiB; nunca modifica una release ni PROD."""
import argparse
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
from datetime import datetime, timezone


SERVICES = ('hubdigital-worker.service', 'hubdigital-r2-signatures.service',
            'php8.4-fpm.service', 'postgresql@16-main.service')
AI_UNITS = ('hubdigital-chat-model.service', 'hubdigital-ia-renovacion.service',
            'hubdigital-ia-renovacion.timer')
FILES = {
    'etc/php/8.4/cli/conf.d/zz-hubdigital-dev-memory.ini':
        '; Solo DESA: cada CLI residente tenia su propia cache de 64 MiB.\n'
        '; FPM conserva OPcache y los limites de documentos no cambian.\n'
        'opcache.enable_cli=0\n',
    'etc/php/8.4/fpm/pool.d/zz-hubdigital-dev-memory.conf':
        '[hubdigital]\n; Serializa solicitudes; nginx conserva la espera.\n'
        'pm=ondemand\npm.max_children=1\npm.process_idle_timeout=10s\n',
    'etc/postgresql/16/main/conf.d/99-hubdigital-dev-memory.conf':
        '# Solo DESA: menor cache, mismas tablas, consultas y conexiones.\n'
        "shared_buffers='48MB'\nmaintenance_work_mem='16MB'\n"
        "autovacuum_work_mem='16MB'\n",
}
for unit in AI_UNITS:
    FILES[f'etc/systemd/system/{unit}.d/zz-hubdigital-dev-memory.conf'] = (
        '[Unit]\n# DESA no ejecuta modelos ni renovacion del presupuesto de PROD.\n'
        'ConditionHost=!labinvepn-dev-vnic\n')


class Profile:
    def __init__(self, root=Path('/'), run=subprocess.run, hostname=socket.gethostname,
                 uid=os.geteuid if hasattr(os, 'geteuid') else lambda: -1):
        self.root, self.runner, self.hostname, self.uid = Path(root), run, hostname, uid

    def command(self, *args):
        return self.runner(list(args), check=True, capture_output=True, text=True,
                           timeout=360).stdout.strip()

    def guard(self):
        if self.uid() != 0:
            raise RuntimeError('Se requiere sudo.')
        env = (self.root / 'etc/hubdigital/hubdigital.env').read_text()
        urls = [line.split('=', 1)[1].strip().strip('\"\'')
                for line in env.splitlines() if line.startswith('APP_URL=')]
        if urls != ['https://dev.labinvepn.org'] and urls != ['https://dev.labinvepn.org/']:
            raise RuntimeError('Perfil rechazado: APP_URL no identifica DESA de forma unica.')
        if self.hostname() != 'labinvepn-dev-vnic':
            raise RuntimeError('Perfil rechazado: esta no es la VM DESA autorizada.')
        mem = (self.root / 'proc/meminfo').read_text().splitlines()
        total = int(next(line.split()[1] for line in mem if line.startswith('MemTotal:')))
        if not 768000 <= total <= 1100000:
            raise RuntimeError('Perfil rechazado: la capacidad no corresponde a DESA de 1 GiB.')

    def write(self, relative, content, mode=0o644):
        target = self.root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        if target.is_symlink():
            raise RuntimeError('No se sustituye una configuracion enlazada.')
        with tempfile.NamedTemporaryFile(dir=target.parent, delete=False) as stream:
            temporary = Path(stream.name)
            stream.write(content)
        try:
            temporary.chmod(mode)
            temporary.replace(target)
        finally:
            temporary.unlink(missing_ok=True)

    def validate(self):
        self.command('/usr/sbin/php-fpm8.4', '-t')
        value = self.command('runuser', '-u', 'postgres', '--',
            '/usr/lib/postgresql/16/bin/postgres', '-D', '/var/lib/postgresql/16/main',
            '-C', 'shared_buffers', '-c', 'config_file=/etc/postgresql/16/main/postgresql.conf')
        if value != '6144':  # PostgreSQL Ubuntu: 48 MiB / bloques de 8 KiB.
            raise RuntimeError('PostgreSQL no reconoce el perfil de 48 MiB.')

    def restart(self):
        self.command('systemctl', 'restart', 'postgresql@16-main.service')
        self.command('systemctl', 'restart', 'php8.4-fpm.service')
        self.command('systemctl', 'start', *SERVICES[:2])
        self.command('systemctl', 'is-active', '--quiet', *SERVICES)

    def apply(self, prepare=False):
        self.guard()
        if not prepare:
            self.command('systemctl', 'is-active', '--quiet', *SERVICES)
        previous = {}
        for relative in FILES:
            target = self.root / relative
            if target.is_symlink():
                raise RuntimeError('No se sustituye una configuracion enlazada.')
            previous[relative] = (target.read_bytes(), target.stat().st_mode & 0o777) if target.exists() else None
        stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
        backup = self.root / f'var/backups/hubdigital/dev-memory-{stamp}'
        backup.mkdir(parents=True, mode=0o700)
        (backup / 'previous.json').write_text(json.dumps({k: None if v is None else
            {'content': v[0].decode(), 'mode': v[1]} for k, v in previous.items()}))
        print(f'RESPALDO_CONFIGURACION={backup}', flush=True)
        stopped = False
        try:
            for relative, content in FILES.items():
                self.write(relative, content.encode())
            self.validate()
            print('CONFIGURACION_DESA_VALIDA', flush=True)
            if prepare:
                # La reconstruccion todavia no tiene release ni consumidores.
                # PostgreSQL y FPM leeran el perfil en su siguiente arranque.
                self.command('systemctl', 'daemon-reload')
                print('PERFIL_DESA_1GB_PREPARADO; sin iniciar ni detener servicios.', flush=True)
                return
            # Espera la salida normal de los dos consumidores; no descarta trabajos.
            stopped = True
            self.command('systemctl', 'stop', *SERVICES[:2])
            self.command('systemctl', 'daemon-reload')
            for unit in AI_UNITS:
                if self.command('systemctl', 'show', unit, '--property=LoadState', '--value') != 'not-found':
                    self.command('systemctl', 'stop', unit)
            self.restart()
        except BaseException:
            for relative, content in previous.items():
                if content is None:
                    (self.root / relative).unlink(missing_ok=True)
                else:
                    self.write(relative, *content)
            self.command('systemctl', 'daemon-reload')
            if stopped:
                self.restart()
            print('PERFIL_RESTAURADO_TRAS_FALLO', flush=True)
            raise
        print('PERFIL_DESA_1GB_APLICADO; firma, ETE, R2 y release conservados.', flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--apply', action='store_true', help='Aplicar solo a la VM DESA autorizada.')
    mode.add_argument('--prepare', action='store_true', help='Preparar DESA antes de configurar PostgreSQL, sin consumidores activos.')
    args = parser.parse_args()
    profile = Profile()
    if args.apply or args.prepare:
        profile.apply(prepare=args.prepare)
    else:
        profile.guard()
        print('DESA_IDENTIFICADA; use --apply para instalar el perfil.')
