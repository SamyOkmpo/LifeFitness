# Vista previa en Vercel

Sirve para ver cada rama en vivo mientras se trabaja. **No es el sitio real.**

- `node preview/build.mjs` genera `preview/dist/`: la plantilla de `index.php` con datos de ejemplo
  (fotos de `v1/imagenes_para_subir`), más las imágenes, los videos y el `js/support.js` que usa la página.
- No ejecuta PHP ni se conecta a la base de datos. Nunca publica `.php`, `.sql`, `admin/` ni `api/`:
  el script se detiene si la salida tiene código PHP, datos de conexión o tipos de archivo no permitidos.
- Los formularios no envían nada: cualquier `.php`, `api/` o petición que no sea GET se responde en el navegador.
- La página lleva `noindex`, un `robots.txt` que bloquea todo y una franja "VISTA PREVIA".

Configuración: `vercel.json` en la raíz.

**Para el sitio real** se pueden borrar esta carpeta y `vercel.json`; no afectan a nada más.
