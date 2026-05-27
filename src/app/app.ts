import { isPlatformBrowser } from '@angular/common';
import {
  AfterViewInit,
  Component,
  inject,
  PLATFORM_ID,
} from '@angular/core';
import Swiper from 'swiper';
import { Autoplay } from 'swiper/modules';

@Component({
  selector: 'app-root',
  imports: [],
  templateUrl: './app.html',
})
export class App implements AfterViewInit {
  private readonly platformId = inject(PLATFORM_ID);

  ngAfterViewInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    this.initFadeInObserver();
    this.initSwiper();
    this.initHamburgerMenu();
    this.setCurrentYear();
  }

  abrirModal(): void {
    const modal = document.getElementById('auth-modal');
    if (modal) {
      modal.style.display = 'flex';
    }
  }

  fecharModal(): void {
    const modal = document.getElementById('auth-modal');
    if (modal) {
      modal.style.display = 'none';
    }
    this.alternarFormulario('login');
  }

  alternarFormulario(tipo: 'login' | 'cadastro'): void {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const modalTitle = document.getElementById('modal-title');

    if (!loginForm || !registerForm || !modalTitle) {
      return;
    }

    if (tipo === 'cadastro') {
      loginForm.classList.add('hidden');
      registerForm.classList.remove('hidden');
      modalTitle.textContent = 'Criar Nova Conta';
    } else {
      registerForm.classList.add('hidden');
      loginForm.classList.remove('hidden');
      modalTitle.textContent = 'Acessar Plataforma';
    }
  }

  toggleSobreExtra(): void {
    const conteudo = document.getElementById('conteudo-extra-sobre');
    const icone = document.getElementById('icone-btn-sobre');
    const texto = document.getElementById('texto-btn-sobre');

    if (!conteudo || !icone || !texto) {
      return;
    }

    if (conteudo.classList.contains('hidden')) {
      conteudo.classList.remove('hidden');
      setTimeout(() => {
        conteudo.classList.remove('opacity-0');
        conteudo.classList.add('opacity-100');
      }, 50);
      texto.textContent = 'Ocultar detalhes';
      icone.classList.replace('fa-chevron-down', 'fa-chevron-up');
    } else {
      conteudo.classList.remove('opacity-100');
      conteudo.classList.add('opacity-0');
      setTimeout(() => {
        conteudo.classList.add('hidden');
      }, 500);
      texto.textContent = 'Conhecer nossa história e equipe';
      icone.classList.replace('fa-chevron-up', 'fa-chevron-down');
    }
  }

  private initFadeInObserver(): void {
    if (typeof IntersectionObserver === 'undefined') {
      document.querySelectorAll('.fade-in').forEach((el) => el.classList.add('visible'));
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
          }
        });
      },
      { threshold: 0.1 }
    );
    document.querySelectorAll('.fade-in').forEach((el) => observer.observe(el));
  }

  private initSwiper(): void {
    new Swiper('.mySwiperClientes', {
      modules: [Autoplay],
      slidesPerView: 1,
      spaceBetween: 20,
      loop: true,
      autoplay: {
        delay: 4000,
        disableOnInteraction: false,
      },
      breakpoints: {
        768: {
          slidesPerView: 2,
          spaceBetween: 30,
        },
        1024: {
          slidesPerView: 3,
          spaceBetween: 40,
        },
      },
    });
  }

  private initHamburgerMenu(): void {
    const hamburgerBtn = document.getElementById('hamburger-menu');
    const navMenu = document.getElementById('nav-menu');

    if (!hamburgerBtn || !navMenu) {
      return;
    }

    hamburgerBtn.addEventListener('click', () => {
      navMenu.classList.toggle('active');
    });

    navMenu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        navMenu.classList.remove('active');
      });
    });
  }

  private setCurrentYear(): void {
    const yearEl = document.getElementById('current-year');
    if (yearEl) {
      yearEl.textContent = String(new Date().getFullYear());
    }
  }
}
