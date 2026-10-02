/** Conserva el nombre original registrado; la URL pública no contiene el formato. */
export function nombreDescargaImagen(nombreOriginal) {
    if (typeof nombreOriginal !== 'string') return 'imagen';
    const nombre = nombreOriginal.replaceAll('\\', '/').split('/').at(-1)
        .replace(/[\u0000-\u001f\u007f<>:"|?*]/g, '_').trim();
    return nombre && nombre !== '.' && nombre !== '..' ? nombre : 'imagen';
}
