function alternarFormulario(tipo) {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const modalTitle = document.getElementById('modal-title');

    if (tipo === 'cadastro') {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        modalTitle.innerText = "Criar Nova Conta";
    } else {
        registerForm.classList.add('hidden');
        loginForm.classList.remove('hidden');
        modalTitle.innerText = "Acessar Plataforma";
    }
}

function fecharModal() {
    document.getElementById('auth-modal').style.display = 'none';
    alternarFormulario('login');
}

function toggleSobreExtra() {
    const conteudo = document.getElementById('conteudo-extra-sobre');
    const icone = document.getElementById('icone-btn-sobre');
    const texto = document.getElementById('texto-btn-sobre');

    if (conteudo.classList.contains('hidden')) {
        // Mostrar
        conteudo.classList.remove('hidden');
        // Timeout curto para garantir que o display mude antes da transição de opacidade
        setTimeout(() => {
            conteudo.classList.remove('opacity-0');
            conteudo.classList.add('opacity-100');
        }, 50);
        texto.textContent = "Ocultar detalhes";
        icone.classList.replace('fa-chevron-down', 'fa-chevron-up');
    } else {
        // Esconder
        conteudo.classList.remove('opacity-100');
        conteudo.classList.add('opacity-0');
        
        // Aguarda a transição terminar para dar 'display: none'
        setTimeout(() => {
            conteudo.classList.add('hidden');
        }, 500); // 500ms é o mesmo tempo do duration-500 do Tailwind
        
        texto.textContent = "Conhecer nossa história e equipe";
        icone.classList.replace('fa-chevron-up', 'fa-chevron-down');
    }
}