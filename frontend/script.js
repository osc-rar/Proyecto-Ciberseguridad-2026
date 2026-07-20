// El frontend y el backend se sirven desde el mismo router.php (mismo origen),
// por lo que la base de la API debe ser el origen REAL desde el que se abrio la
// pagina. Hardcodear 'http://localhost:8080' rompia la app al accederla desde
// otra maquina: en el navegador de Kali apuntando a la IP de la victima,
// 'localhost' resuelve a la propia Kali (no al servidor victima) y los fetch
// fallaban. window.location.origin funciona tanto en local como en las VMs.
const API_BASE = window.location.origin;

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
            // SEGURO EN ESTA RAMA (defensa del lado del servidor).
            // Se sigue usando innerHTML, pero los datos ya llegan neutralizados:
            // ver_portafolio.php valida el registro con perfil_es_valido() y luego
            // aplica escapar_valores_recursivo(), que pasa htmlspecialchars() con
            // ENT_QUOTES sobre todos los strings del perfil. Por eso un payload
            // como <script>alert(1)</script> guardado en el nombre o la bio viaja
            // como &lt;script&gt;... y el navegador lo pinta como texto inerte,
            // sin ejecutarlo. La mitigacion de CWE-79 esta en el servidor.
            // RECOMENDACION (opcional, defensa en profundidad): migrar a
            // textContent o a la creacion de nodos con createElement, para no
            // depender de que el escape del servidor se mantenga en el futuro.
            perfilInfo.innerHTML = `
                <h3>${data.perfil.nombre} ${data.perfil.apellido}</h3>
                <p>${data.perfil.bio}</p>
                <small>Registrado: ${data.perfil.fecha_registro}</small>
            `;
        }

        if (data.repos && data.repos.length > 0) {
            // SEGURO EN ESTA RAMA (defensa del lado del servidor).
            // El campo 'descripcion' sigue siendo el vector principal: si la API
            // mock es comprometida via POST /update_repos, puede devolver
            // <script>alert('XSS')</script>. Sin embargo ver_portafolio.php ya no
            // confia ciegamente en esa respuesta (OWASP API10:2023): filtra los
            // elementos con repo_es_valido() -que ademas exige que 'url' sea una
            // URL http/https valida, protegiendo el atributo href- y escapa todos
            // los strings con escapar_valores_recursivo(). El payload llega
            // codificado y se muestra como texto literal dentro de la tarjeta.
            // RECOMENDACION (opcional, defensa en profundidad): construir las
            // tarjetas con createElement + textContent, de modo que el frontend
            // sea seguro por si mismo aunque cambie el contrato del backend.
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
