# Plan de Desarrollo - Version Vulnerable

## Fase 1: API Mock (Python)

- [ ] 1.1 Crear archivo `api/api_mock.py` con servidor HTTP basico en puerto 5000
- [ ] 1.2 Definir endpoint GET `/repos` que retorne JSON con repositorios de ejemplo (nombre, descripcion, url, lenguaje)
- [ ] 1.3 Probar que la API responda correctamente con `curl http://localhost:5000/repos`

## Fase 2: Backend PHP Vulnerable

- [ ] 2.1 Crear archivo `backend/crear_perfil.php` que reciba `$_POST['url_repositorio']`
- [ ] 2.2 Implementar `file_get_contents($_POST['url_repositorio'])` sin ninguna validacion (SSRF)
- [ ] 2.3 Guardar datos del perfil en `data/usuarios.txt`
- [ ] 2.4 Crear archivo `backend/ver_portafolio.php` que consuma la API mock (`http://localhost:5000/repos`)
- [ ] 2.5 Decodificar JSON y pasar datos al frontend sin sanitizar
- [ ] 2.6 Agregar comentarios en cada linea vulnerable explicando el fallo y que falta

## Fase 3: Frontend Vulnerable

- [ ] 3.1 Crear `frontend/index.html` con formulario para crear perfil (campo URL repositorio)
- [ ] 3.2 Crear `frontend/styles.css` con estilos basicos
- [ ] 3.3 Crear `frontend/script.js` que consuma `/ver_portafolio.php` y renderice con `innerHTML` sin escape
- [ ] 3.4 Verificar que el flujo completo funciona: crear perfil -> ver portafolio

## Fase 4: Pruebas de Vulnerabilidades

- [ ] 4.1 Probar SSRF: enviar POST con `url_repositorio=http://localhost:5000` y verificar que el servidor actua como proxy
- [ ] 4.2 Probar SSRF: intentar acceder a `http://127.0.0.1:22` u otras rutas internas
- [ ] 4.3 Modificar `api_mock.py` para inyectar `<script>alert('XSS')</script>` en el campo `descripcion`
- [ ] 4.4 Verificar que el XSS se ejecuta al cargar el portafolio

## Fase 5: Documentacion en Codigo

- [ ] 5.1 Agregar comentarios en `crear_perfil.php` explicando por que `file_get_contents()` sin validacion es vulnerable (CWE-918)
- [ ] 5.2 Agregar comentarios en `ver_portafolio.php` explicando la confianza ciega en la API (CWE-20)
- [ ] 5.3 Agregar comentarios en `script.js` explicando por que `innerHTML` sin escape es vulnerable (CWE-79)
- [ ] 5.4 Mapear cada vulnerabilidad a su entrada en OWASP Top 10 / API Security Top 10

## Fase 6: Preparacion para Demo

- [ ] 6.1 Crear script `payloads.txt` con los payloads de SSRF y XSS utilizados
- [ ] 6.2 Documentar instrucciones de despliegue en VMs (Kali + Ubuntu Server)
- [ ] 6.3 Preparar demo del ataque SSRF paso a paso
- [ ] 6.4 Preparar demo del ataque XSS via API comprometida
