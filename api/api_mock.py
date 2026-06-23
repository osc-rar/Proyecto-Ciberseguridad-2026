#!/usr/bin/env python3
"""
api_mock.py
API externa simulada para el proyecto de ciberseguridad.
Corre en el puerto 5000 de la maquina victima.

NOTA DE SEGURIDAD: Esta API es intencionalmente insegura por diseño.
El endpoint /update_repos no implementa autenticacion ni validacion,
permitiendo modificar el contenido entregado a la aplicacion principal.
Esto simula una API de terceros comprometida y permite demostrar el
riesgo de Consumo No Seguro de APIs (API10:2023) en la fase de ataque.
"""

import json
from http.server import BaseHTTPRequestHandler, HTTPServer

# Repositorios iniciales que la API expone a la aplicacion principal.
# El campo 'descripcion' sera el vector de ataque para la demostracion de XSS.
REPOS = [
    {
        "nombre": "proyecto-ejemplo",
        "descripcion": "Este es un repositorio de ejemplo legado.",
        "url": "https://github.com/usuario/proyecto-ejemplo",
        "lenguaje": "PHP"
    },
    {
        "nombre": "api-client",
        "descripcion": "Cliente para consumir servicios externos.",
        "url": "https://github.com/usuario/api-client",
        "lenguaje": "JavaScript"
    }
]


class APIHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        # Sobreescribimos para evitar logs ruidosos en la demo.
        pass

    def _send_json(self, status, data):
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        # Cabecera CORS muy permisiva: permite que cualquier origen (incluido
        # el navegador del atacante) consuma este servicio sin restricciones.
        # En un entorno real esto seria peligroso; aqui se usa para facilitar
        # la demostracion del flujo de ataque.
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        self.wfile.write(json.dumps(data).encode("utf-8"))

    def do_GET(self):
        if self.path == "/repos":
            self._send_json(200, REPOS)
        elif self.path == "/":
            self._send_json(200, {"message": "API Mock activa", "endpoints": ["/repos", "/update_repos"]})
        else:
            self._send_json(404, {"error": "Endpoint no encontrado"})

    def do_POST(self):
        global REPOS
        if self.path == "/update_repos":
            content_length = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(content_length)
            try:
                payload = json.loads(body)
                # VULNERABLE: No se valida la estructura del JSON recibido.
                # Cualquier dato enviado reemplaza la lista de repositorios.
                # Esto simula un tercero malicioso (o una API comprometida)
                # inyectando contenido arbitrario.
                REPOS = payload if isinstance(payload, list) else [payload]
                self._send_json(200, {"status": "repositorios actualizados", "repos": REPOS})
            except json.JSONDecodeError:
                self._send_json(400, {"error": "JSON invalido"})
        else:
            self._send_json(404, {"error": "Endpoint no encontrado"})


if __name__ == "__main__":
    import sys
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 5000
    server_address = ("0.0.0.0", port)
    httpd = HTTPServer(server_address, APIHandler)
    print(f"API Mock corriendo en http://0.0.0.0:{port}")
    httpd.serve_forever()
