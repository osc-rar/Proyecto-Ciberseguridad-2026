# Analisis de Impacto Tecnico

Proyecto de Ciberseguridad UCAB 2026 — rama `versión-vulnerable`.

Impacto tecnico de cada vulnerabilidad, descrito en tres niveles — **red**,
**servidor** y **cliente/navegador** — y basado unicamente en lo que el codigo real
permite. El objetivo es lenguaje claro y no exagerado: se distingue lo que la app
demuestra en el laboratorio de lo que un atacante podria lograr en un entorno real.

Para el guion de explotacion ver [demo_ssrf.md](demo_ssrf.md) y
[demo_xss_api.md](demo_xss_api.md); para el flujo de datos,
[FLUJO_DATOS.md](FLUJO_DATOS.md).

---

## 1. SSRF — `backend/crear_perfil.php` (API7:2023 / CWE-918)

El servidor hace una peticion HTTP (o `file://`) hacia el destino que indique el
atacante y le **devuelve el resultado** en `preview_repositorio`. Esto convierte al
servidor en un proxy/oraculo controlado por el atacante.

### Nivel de red
- **Descubrimiento de servicios internos.** Comparando `estado_conexion`,
  `estado_http_externo` y `preview_repositorio` para distintos puertos
  (`127.0.0.1:5000`, `:22`, `:6379`, `:9999`), el atacante mapea que servicios
  internos existen sin escanear la red directamente. En el laboratorio esto revela
  la API mock (5000) y, segun la VM, SSH (22).
- **Alcance a otras maquinas.** El destino puede ser otra IP de la red host-only
  (p. ej. `http://192.168.56.x:80`), por lo que el servidor puede sondear equipos
  que el atacante quiza no alcanza igual de bien de forma directa.

### Nivel de servidor
- **Servidor como proxy/oraculo.** El servidor victima realiza peticiones "en
  nombre" del atacante y filtra las diferencias de respuesta (timing, estado,
  cuerpo). Es exactamente el patron de un oraculo de escaneo.
- **Lectura de archivos locales.** `file_get_contents()` acepta `file://`, de modo
  que se pueden leer archivos del sistema (`/etc/passwd`) y datos de la propia app
  (`data/usuarios.txt`), que se exfiltran en los primeros 500 caracteres del
  `preview_repositorio`.
- **Riesgo secundario de integridad del almacenamiento.** Los campos del perfil se
  guardan sin sanitizar en `usuarios.txt` (un JSON por linea); saltos de linea
  inyectados podrian corromper el formato del archivo.

### Nivel de cliente/navegador
- **No aplica directamente.** Esta vulnerabilidad la explota el atacante contra el
  servidor; no requiere una victima navegando. Su valor para el resto de la cadena
  es de **reconocimiento**: confirma la existencia de la API interna en `:5000` que
  luego se compromete para el XSS (ver seccion 2 y
  [METODOLOGIA_MITRE_ATTACK.md](METODOLOGIA_MITRE_ATTACK.md)).

### Alcance real vs. laboratorio
En cloud (AWS/GCP/Azure) el mismo fallo permitiria leer el endpoint de metadatos
(`169.254.169.254`) y potencialmente credenciales de instancia; en las VMs locales
ese destino devuelve `error_conexion`. Es una diferencia de entorno, no de la
vulnerabilidad.

---

## 2. Consumo No Seguro de APIs → XSS (API10:2023 / CWE-20 → CWE-79)

El atacante compromete la API de terceros (`/update_repos`), el backend reenvia ese
contenido sin sanitizar y el frontend lo inserta con `innerHTML`, ejecutando
JavaScript en el navegador de la victima.

### Nivel de red
- **Compromiso de un componente interno alcanzable.** La API escucha en
  `0.0.0.0:5000`, asi que el atacante en la red host-only puede llamar
  `/update_repos` directamente. El impacto de red es que un servicio que "deberia"
  ser interno esta expuesto y es modificable sin credenciales.

### Nivel de servidor
- **Confianza ciega en un tercero.** `ver_portafolio.php` asume que todo lo que
  devuelve la API es seguro: no valida estructura ni tipos y reenvia el JSON tal
  cual. El servidor se convierte en el **canal de entrega** del payload hacia cada
  cliente que cargue el portafolio (efecto tipo "almacenado": afecta a todas las
  victimas hasta que se reinicia la API).
- **Persistencia del payload.** El contenido malicioso permanece en la lista
  `REPOS` en memoria hasta reiniciar `api_mock.py`, por lo que el ataque no es de
  una sola vez.

### Nivel de cliente/navegador
- **Ejecucion de JavaScript arbitrario** en el contexto (origen) de la aplicacion
  victima. El `alert(document.domain)` de la demo evidencia que el codigo corre en
  el origen de la victima, no en el del atacante.
- **Que podria robar un atacante real** (lo que el contexto de ejecucion permite):
  - **Cookies de sesion** accesibles a JS (`document.cookie`) — la variante 5b de
    [demo_xss_api.md](demo_xss_api.md) las exfiltra a un listener del atacante.
    *Matiz:* cookies marcadas `HttpOnly` no serian accesibles; esta app no
    establece cookies de sesion propias, por lo que en el laboratorio el robo es
    demostrativo del **mecanismo**.
  - **Tokens u otros datos en `localStorage`/`sessionStorage`**, si la app los
    usara.
  - **Acciones en nombre de la victima**: el JS puede lanzar peticiones autenticadas
    al backend (las cabeceras CORS son permisivas), realizar phishing modificando el
    DOM, o redirigir a sitios controlados por el atacante.

### Alcance real vs. laboratorio
En una aplicacion real con sesiones autenticadas, el mismo XSS permitiria secuestro
de sesion, acciones no autorizadas y robo de datos del usuario. En este laboratorio
la app no maneja autenticacion, por lo que la demo prueba la **ejecucion** y el
**mecanismo de exfiltracion**, no un robo de sesion real.

---

## Resumen

| Vulnerabilidad | Red | Servidor | Cliente/navegador |
|---|---|---|---|
| SSRF (CWE-918) | Descubrimiento de servicios internos y otras VMs | Servidor como proxy/oraculo; lectura de archivos locales | No aplica directamente (habilita el reconocimiento) |
| Consumo inseguro → XSS (CWE-20/79) | Servicio interno modificable sin auth | Entrega ciega y persistente del payload | Ejecucion de JS arbitrario; robo potencial de cookies/tokens y acciones en nombre de la victima |

Las contramedidas de cada capa estan resumidas en las tablas de "Mitigacion" de
[demo_ssrf.md](demo_ssrf.md) y [demo_xss_api.md](demo_xss_api.md), e implementadas en
la rama `versión-asegurada`.
