const API_BASE = 'http://localhost:8080';

const formPerfil = document.getElementById('form-perfil');
const resultadoCrear = document.getElementById('resultado-crear');
const btnCargarPortafolio = document.getElementById('btn-cargar-portafolio');
const perfilInfo = document.getElementById('perfil-info');
const listaRepos = document.getElementById('lista-repos');

formPerfil.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(formPerfil);

    try {
        const response = await fetch(`${API_BASE}/backend/crear_perfil.php`, {
            method: 'POST',
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            resultadoCrear.className = 'exito';
            resultadoCrear.textContent = `Perfil creado. Estado conexion: ${data.estado_conexion}`;
        } else {
            resultadoCrear.className = 'error';
            resultadoCrear.textContent = data.error || 'Error al crear perfil.';
        }
    } catch (err) {
        resultadoCrear.className = 'error';
        resultadoCrear.textContent = 'No se pudo conectar con el servidor.';
    }
});

btnCargarPortafolio.addEventListener('click', async () => {
    try {
        const response = await fetch(`${API_BASE}/backend/ver_portafolio.php`);
        const data = await response.json();

        if (!response.ok) {
            listaRepos.innerHTML = `<p style="color:#f85149;">${data.error}</p>`;
            return;
        }

        if (data.perfil) {
            perfilInfo.classList.add('visible');
            // VULNERABLE (CWE-79): Se asignan los valores directamente a innerHTML
            // sin escapar con textContent o DOMPurify. Si el perfil contiene HTML
            // inyectado (por ejemplo en el nombre o la bio), el navegador lo renderiza.
            // FALTA: usar textContent o sanitizar con DOMPurify antes de insertar.
            perfilInfo.innerHTML = `
                <h3>${data.perfil.nombre} ${data.perfil.apellido}</h3>
                <p>${data.perfil.bio}</p>
                <small>Registrado: ${data.perfil.fecha_registro}</small>
            `;
        }

        if (data.repos && data.repos.length > 0) {
            // VULNERABLE (CWE-79): Los campos del repositorio se insertan directamente
            // en innerHTML sin ningun escape. El campo 'descripcion' es el vector
            // principal de ataque: si la API mock fue comprometida (mediante
            // /update_repos), puede contener <script>alert('XSS')</script> o
            // cualquier payload JavaScript que se ejecutara en el navegador.
            // FALTA: usar textContent para cada campo o sanitizar con DOMPurify.
            listaRepos.innerHTML = data.repos.map(repo => `
                <div class="repo-card">
                    <h3>${repo.nombre}</h3>
                    <p>${repo.descripcion}</p>
                    <span class="lenguaje">${repo.lenguaje}</span>
                    <br>
                    <a href="${repo.url}" target="_blank">Ver repositorio</a>
                </div>
            `).join('');
        } else {
            listaRepos.innerHTML = '<p>No se encontraron repositorios.</p>';
        }
    } catch (err) {
        listaRepos.innerHTML = '<p style="color:#f85149;">No se pudo cargar el portafolio.</p>';
    }
});
