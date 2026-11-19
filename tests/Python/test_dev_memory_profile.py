"""Pruebas locales del perfil operativo; no ejecutan systemctl ni SSH reales."""
import importlib.util
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[2] / 'deploy/oracle/scripts/configure-dev-memory.py'
BOOTSTRAP = SCRIPT.parent / 'configure-postgres.sh'
spec = importlib.util.spec_from_file_location('dev_memory', SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class DevMemoryTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='hubdigital-dev-memory-')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        for relative, text in {
            'etc/hubdigital/hubdigital.env': 'APP_URL=https://dev.labinvepn.org\nPRIVATE=synthetic\n',
            'proc/meminfo': 'MemTotal: 975000 kB\n',
            'etc/php/8.4/fpm/pool.d/hubdigital.conf': '[hubdigital]\nphp_admin_value[memory_limit]=192M\n',
            'srv/hubdigital/current/SOURCE-METADATA.json': '{"commit":"release-inmutable"}',
        }.items():
            target = self.root / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(text)
        self.calls, self.failed = [], False
        self.fail = None
        self.profile = module.Profile(self.root, run=self.run_command,
            hostname=lambda: 'labinvepn-dev-vnic', uid=lambda: 0)

    def run_command(self, args, **kwargs):
        self.calls.append(args)
        self.assertTrue(kwargs['check'])
        self.assertLessEqual(kwargs['timeout'], 360)
        if self.fail and self.fail in ' '.join(args) and not self.failed:
            self.failed = True
            raise subprocess.CalledProcessError(1, args)
        output = '6144' if '-C' in args else 'loaded' if 'show' in args else ''
        return subprocess.CompletedProcess(args, 0, output, '')

    def snapshot(self):
        return {p: (self.root / p).read_bytes() if (self.root / p).exists() else None
                for p in module.FILES}

    def test_instala_solo_perfil_conserva_limites_release_y_consumidores(self):
        base = self.root / 'etc/php/8.4/fpm/pool.d/hubdigital.conf'
        metadata = self.root / 'srv/hubdigital/current/SOURCE-METADATA.json'
        original = (base.read_bytes(), metadata.read_bytes())
        self.profile.apply()
        self.assertEqual(original, (base.read_bytes(), metadata.read_bytes()))
        self.assertIn('opcache.enable_cli=0', (self.root / next(iter(module.FILES))).read_text())
        fpm = (self.root / 'etc/php/8.4/fpm/pool.d/zz-hubdigital-dev-memory.conf').read_text()
        self.assertIn('pm.max_children=1', fpm)
        self.assertIn('pm=ondemand', fpm)
        self.assertIn(['systemctl', 'start', *module.SERVICES[:2]], self.calls)
        for call in self.calls:
            self.assertNotIn('hubdigital-signature.service', call)
            self.assertNotIn('hubdigital-ete.service', call)
            self.assertNotIn('hubdigital-r2-transport.service', call)
        self.assertFalse(any('artisan' in call for call in self.calls))
        backups = list((self.root / 'var/backups/hubdigital').glob('*/previous.json'))
        self.assertEqual(len(backups), 1)
        self.assertNotIn('synthetic', backups[0].read_text())

    def test_rechaza_prod_antes_de_cualquier_mutacion(self):
        (self.root / 'etc/hubdigital/hubdigital.env').write_text('APP_URL=https://labinvepn.org\n')
        with self.assertRaisesRegex(RuntimeError, 'APP_URL'):
            self.profile.apply()
        self.assertEqual(self.calls, [])
        self.assertTrue(all(value is None for value in self.snapshot().values()))

    def test_rechaza_dominio_duplicado_aunque_incluya_desa(self):
        with (self.root / 'etc/hubdigital/hubdigital.env').open('a') as stream:
            stream.write('APP_URL=https://labinvepn.org\n')
        with self.assertRaisesRegex(RuntimeError, 'unica'):
            self.profile.apply()
        self.assertEqual(self.calls, [])

    def test_rechaza_otra_vm_y_capacidad_de_prod(self):
        self.profile.hostname = lambda: 'instance-labinvepn-produccion'
        with self.assertRaisesRegex(RuntimeError, 'VM DESA'):
            self.profile.apply()
        self.profile.hostname = lambda: 'labinvepn-dev-vnic'
        (self.root / 'proc/meminfo').write_text('MemTotal: 12211200 kB\n')
        with self.assertRaisesRegex(RuntimeError, 'capacidad'):
            self.profile.apply()
        self.assertEqual(self.calls, [])

    def test_requiere_root(self):
        self.profile.uid = lambda: 1000
        with self.assertRaisesRegex(RuntimeError, 'sudo'):
            self.profile.apply()
        self.assertEqual(self.calls, [])

    def test_fpm_invalido_restaurado_sin_detener_servicios(self):
        self.fail = '/usr/sbin/php-fpm8.4 -t'
        before = self.snapshot()
        with self.assertRaises(subprocess.CalledProcessError):
            self.profile.apply()
        self.assertEqual(self.snapshot(), before)
        self.assertFalse(any('stop' in call or 'restart' in call for call in self.calls))

    def test_fallo_arranque_restaura_configuracion_previa_y_servicios(self):
        first = next(iter(module.FILES))
        (self.root / first).parent.mkdir(parents=True, exist_ok=True)
        (self.root / first).write_text('; configuracion anterior\n')
        before = self.snapshot()
        self.fail = 'restart postgresql@16-main.service'
        with self.assertRaises(subprocess.CalledProcessError):
            self.profile.apply()
        self.assertEqual(self.snapshot(), before)
        self.assertEqual(self.calls.count(['systemctl', 'restart', 'postgresql@16-main.service']), 2)
        self.assertEqual(self.calls[-1], ['systemctl', 'is-active', '--quiet', *module.SERVICES])

    def test_reaplicar_no_duplica_directivas(self):
        self.profile.apply()
        before = self.snapshot()
        self.profile.apply()
        self.assertEqual(self.snapshot(), before)

    def test_reconstruccion_prepara_sin_release_ni_servicios_activos(self):
        (self.root / 'srv/hubdigital/current/SOURCE-METADATA.json').unlink()
        self.profile.apply(prepare=True)
        self.assertTrue(all(value is not None for value in self.snapshot().values()))
        self.assertEqual([call for call in self.calls if call[0] == 'systemctl'],
                         [['systemctl', 'daemon-reload']])
        self.assertIn("shared_buffers='48MB'", (self.root /
            'etc/postgresql/16/main/conf.d/99-hubdigital-dev-memory.conf').read_text())

    def test_reconstruccion_rechaza_prod_sin_mutaciones(self):
        (self.root / 'etc/hubdigital/hubdigital.env').write_text('APP_URL=https://labinvepn.org\n')
        with self.assertRaisesRegex(RuntimeError, 'APP_URL'):
            self.profile.apply(prepare=True)
        self.assertEqual(self.calls, [])
        self.assertTrue(all(value is None for value in self.snapshot().values()))

    def test_reconstruccion_invalida_restaura_sin_arrancar_servicios(self):
        self.fail = '/usr/sbin/php-fpm8.4 -t'
        before = self.snapshot()
        with self.assertRaises(subprocess.CalledProcessError):
            self.profile.apply(prepare=True)
        self.assertEqual(self.snapshot(), before)
        self.assertFalse(any('stop' in call or 'start' in call or 'restart' in call
                             for call in self.calls))

    def test_no_sigue_enlace_a_archivo_ajeno(self):
        first = self.root / next(iter(module.FILES))
        first.parent.mkdir(parents=True, exist_ok=True)
        protected = self.root / 'protected'
        protected.write_text('conservar')
        # La rama de rechazo es comprobable tambien en Windows sin privilegio
        # de crear symlinks; Path.is_symlink es la frontera del sistema operativo.
        with patch.object(Path, 'is_symlink', lambda p: p == first):
            with self.assertRaisesRegex(RuntimeError, 'enlazada'):
                self.profile.apply()
        self.assertEqual(protected.read_text(), 'conservar')


class PostgresBootstrapTest(unittest.TestCase):
    """Ejecuta el flujo Bash con servicios/SQL simulados, sin privilegios Linux."""
    def bootstrap(self, url, prepare_status=0):
        bash = (Path('C:/Program Files/Git/bin/bash.exe') if os.name == 'nt'
                else Path(shutil.which('bash') or '/missing-bash'))
        if not bash.is_file():
            self.fail('Falta Bash para comprobar la integracion de reconstruccion.')
        with tempfile.TemporaryDirectory(prefix='hd-pg-bootstrap-') as directory:
            root = Path(directory)
            env_file = root / 'hubdigital.env'
            env_file.write_text(f'APP_URL={url}\nDB_DATABASE=synthetic\nDB_USERNAME=synthetic\nDB_PASSWORD=synthetic\n')
            source = BOOTSTRAP.read_text(encoding='utf-8')
            # Solo sustituye la guarda root y la ruta privada. El flujo completo
            # de seleccion, preparacion, reinicio y comprobacion SQL es el real.
            source = source.replace('if [[ "${EUID}" -ne 0 ]]; then', 'if false; then')
            source = source.replace('env_file=/etc/hubdigital/hubdigital.env',
                                    'env_file="$2"')
            script = root / 'configure-postgres.sh'
            script.write_text(source, encoding='utf-8', newline='\n')
            calls = root / 'calls.log'
            harness = r'''
set -euo pipefail
call_log="$3"
systemctl() { printf 'systemctl %s\n' "$*" >> "$call_log"; }
prepare_status="$4"
python3() { printf 'python3 %s\n' "$*" >> "$call_log"; return "$prepare_status"; }
runuser() {
 case "$*" in
  *'SHOW listen_addresses'*) echo 127.0.0.1 ;;
  *'SHOW max_connections'*) echo 20 ;;
  *'SHOW shared_buffers'*) echo "$shared_buffers_expected" ;;
  *'SHOW work_mem'*) echo 2MB ;;
  *) cat >/dev/null ;;
 esac
}
source "$1"
'''
            result = subprocess.run([str(bash), '-c', harness, 'bootstrap-test',
                script.as_posix(), env_file.as_posix(), calls.as_posix(), str(prepare_status)],
                capture_output=True, text=True, timeout=15)
            return result, calls.read_text() if calls.exists() else ''

    def test_desa_prepara_antes_de_arrancar_postgres(self):
        for url in ['https://dev.labinvepn.org', 'https://dev.labinvepn.org/']:
            with self.subTest(url=url):
                result, calls = self.bootstrap(url)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertIn('configure-dev-memory.py --prepare', calls)
                self.assertLess(calls.index('python3'), calls.index('systemctl restart'))

    def test_prod_no_instala_el_perfil_desa(self):
        result, calls = self.bootstrap('https://labinvepn.org')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertNotIn('python3', calls)
        self.assertIn('systemctl restart postgresql@16-main.service', calls)

    def test_fallo_del_perfil_detiene_configuracion_antes_de_reiniciar(self):
        result, calls = self.bootstrap('https://dev.labinvepn.org', prepare_status=1)
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('systemctl', calls)


if __name__ == '__main__':
    unittest.main()
