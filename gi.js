// ==========================================
// ARQUIVO PRINCIPAL: gi.js
// ==========================================

// 1. Eventos que precisam carregar junto com a página (Barra de Progresso e FAQ)
document.addEventListener("DOMContentLoaded", () => {
     // --- Animação Dinâmica da Barra de Progresso ---
     const progressBar = document.getElementById("progressBar");
     if (progressBar) { // Evita erro se a barra não existir
          // Pega a porcentagem real gerada pelo PHP no atributo data-percent
          const percentualArrecadado = progressBar.getAttribute('data-percent');

          // Delay para a animação rodar após o site carregar
          setTimeout(() => {
               progressBar.style.width = percentualArrecadado;
          }, 300);
     }

     // --- Lógica do FAQ (Acordeão) ---
     const faqItems = document.querySelectorAll('.faq-question');

     faqItems.forEach(item => {
          item.addEventListener('click', () => {
               // Alterna a classe ativa no título (para girar a setinha)
               item.classList.toggle('active');

               // Pega a div da resposta logo abaixo
               const answer = item.nextElementSibling;

               // Alterna a classe que abre e fecha a resposta
               answer.classList.toggle('open');
          });
     });

     // --- Lógica "Leia mais" na História ---
     const btnLeiaMais = document.getElementById('btn-leia-mais');
     const restanteHistoria = document.getElementById('restante-historia');

     if (btnLeiaMais && restanteHistoria) {
          btnLeiaMais.addEventListener('click', () => {
               restanteHistoria.style.display = 'block';
               restanteHistoria.style.animation = 'fadeInUp 0.6s ease forwards';
               btnLeiaMais.style.display = 'none';
          });
     }

     // --- Lógica do Countdown (Contagem regressiva) ---
     const countdownEl = document.getElementById('countdown-urgency');
     if (countdownEl) {
          // Data alvo: 4 semanas após 08/04/2026 00:00:00
          const startDate = new Date("2026-04-08T00:00:00").getTime();
          const targetDate = startDate + (4 * 7 * 24 * 60 * 60 * 1000); // 4 semanas em milissegundos

          function updateCountdown() {
               const now = new Date().getTime();
               const distance = targetDate - now;

               if (distance < 0) {
                    countdownEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> O prazo terminou';
                    return;
               }

               const days = Math.floor(distance / (1000 * 60 * 60 * 24));
               const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
               const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
               const seconds = Math.floor((distance % (1000 * 60)) / 1000);

               countdownEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> URGENTE! FALTAM: ${days}d ${hours}h ${minutes}m ${seconds}s`;
          }

          updateCountdown(); // Call immediately
          setInterval(updateCountdown, 1000); // Update every second
     }
});

// ----------------------------------------------------
// 2. Função de Copiar o PIX (Copia e Cola Gigante - Card 2)
// ----------------------------------------------------
function copyPix() {
     const pixInput = document.getElementById("pixCode");
     const btnCopy = document.getElementById("btnCopy");

     if (!pixInput || !btnCopy) return;

     // Seleciona o texto no input
     pixInput.select();
     pixInput.setSelectionRange(0, 99999); // Suporte para mobile

     try {
          // Método tradicional de cópia (Funciona em testes locais e online)
          document.execCommand('copy');

          // Feedback Visual
          const iconeOriginal = btnCopy.innerHTML;
          btnCopy.innerHTML = '<i class="fas fa-check"></i>';
          btnCopy.style.backgroundColor = "var(--success)"; // Fica verde

          // Retorna ao normal após 2.5 segundos
          setTimeout(() => {
               btnCopy.innerHTML = iconeOriginal;
               btnCopy.style.backgroundColor = "";
          }, 2500);
     } catch (err) {
          console.error('Erro ao copiar: ', err);
          alert('Não foi possível copiar o código PIX. Por favor, tente selecionar e copiar manualmente.');
     }
}

// ----------------------------------------------------
// 3. Função para copiar a Chave PIX simples (Card 1 - Email)
// ----------------------------------------------------
function copyPixKeyEmail() {
     const pixInput = document.getElementById("pixKeyEmail");
     const btnCopy = document.getElementById("btnCopyEmail");

     if (!pixInput || !btnCopy) return;

     // Seleciona o texto no input
     pixInput.select();
     pixInput.setSelectionRange(0, 99999); // Suporte para mobile

     try {
          // Método tradicional de cópia (Funciona em testes locais e online)
          document.execCommand('copy');

          // Feedback Visual
          const iconeOriginal = btnCopy.innerHTML;
          btnCopy.innerHTML = '<i class="fas fa-check"></i>';
          btnCopy.style.backgroundColor = "var(--success)"; // Fica verde

          // Retorna ao normal após 2.5 segundos
          setTimeout(() => {
               btnCopy.innerHTML = iconeOriginal;
               btnCopy.style.backgroundColor = "";
          }, 2500);
     } catch (err) {
          console.error('Erro ao copiar: ', err);
          alert('Não foi possível copiar a Chave PIX. Por favor, tente selecionar e copiar manualmente.');
     }
}

// ----------------------------------------------------
// 4. Função de Compartilhamento Rápido (WhatsApp)
// ----------------------------------------------------
function compartilharZap() {
     // Monta o texto já com urgência e emoção
     const texto = "Olá! A Gideane descobriu um tumor maligno no olho e precisa de uma cirurgia urgente em até 4 semanas. Você pode ajudar doando qualquer valor ou compartilhando? Conheça a história e ajude a salvar a Gi: " + window.location.href;

     // Abre o WhatsApp do celular do usuário pronto para enviar
     window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(texto)}`, '_blank');
}

// ==========================================
// 5. NOVA FUNÇÃO: GALERIA HERO (Troca Foto/Vídeo e Animação)
// ==========================================
function trocarMidia(tipo, url, elementoThumb) {
     const display = document.getElementById('mainMediaDisplay');

     if (!display) return; // Previne erros caso a galeria não exista

     // Efeito de fade-out antes de trocar
     display.style.opacity = '0';
     display.style.transition = 'opacity 0.3s ease';

     // Pequeno delay para a troca acontecer com fade
     setTimeout(() => {
          // Troca o conteúdo principal
          if (tipo === 'video') {
               display.innerHTML = `<iframe src="https://www.youtube.com/embed/${url}?autoplay=1&modestbranding=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen title="Vídeo Principal" style="width: 100%; aspect-ratio: 16/9; border-radius: 8px;"></iframe>`;
          } else {
               // Usa 'contain' para manter foto proporcional sem cortes
               display.innerHTML = `<img src="${url}" alt="Imagem da Galeria Gi" style="width: 100%; height: auto; max-height: 400px; object-fit: contain; border-radius: 8px; display: block; margin: 0 auto;">`;
          }

          // Fade-in após a troca
          display.style.opacity = '1';

          // Atualiza as miniaturas (remove 'active' de todas e coloca na clicada)
          document.querySelectorAll('.media-thumb, .media-thumb-video').forEach(t => t.classList.remove('active'));
          if (elementoThumb) {
               elementoThumb.classList.add('active');
          }

     }, 300); // Mesmo tempo do fade-out
}

// ==========================================
// 7. NOVA FUNÇÃO: CARREGAR MAIS ATUALIZAÇÕES
// ==========================================
document.addEventListener("DOMContentLoaded", () => {
     const btnLoadMore = document.getElementById('btnLoadMoreUpdates');

     if (btnLoadMore) {
          btnLoadMore.addEventListener('click', () => {
               // Pega todas as atualizações que estão com a classe 'hidden-update'
               const hiddenItems = document.querySelectorAll('.hidden-update');

               // Mostra apenas os próximos 10 itens
               for (let i = 0; i < 10 && i < hiddenItems.length; i++) {
                    hiddenItems[i].classList.remove('hidden-update');
                    hiddenItems[i].style.animation = 'fadeInUp 0.6s ease forwards';
               }

               // Se não houver mais nenhum escondido, some com o botão
               if (document.querySelectorAll('.hidden-update').length === 0) {
                    btnLoadMore.style.display = 'none';
               }
          });
     }
});

// ==========================================
// 8. FUNÇÕES DO MODAL DE VÍDEO
// ==========================================
function openVideoModal(videoId) {
     const modal = document.getElementById('videoModal');
     const container = document.getElementById('videoModalContainer');

     if (modal && container) {
          // Injeta o iframe com autoplay
          container.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&modestbranding=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
          // Mostra o modal
          modal.classList.add('active');
          // Impede rolagem do fundo
          document.body.style.overflow = 'hidden';
     }
}

function closeVideoModal() {
     const modal = document.getElementById('videoModal');
     const container = document.getElementById('videoModalContainer');

     if (modal && container) {
          // Esconde o modal
          modal.classList.remove('active');
          // Remove o iframe para parar o vídeo
          container.innerHTML = '';
          // Restaura a rolagem
          document.body.style.overflow = '';
     }
}

// Fechar modal ao clicar fora do conteúdo
document.addEventListener('DOMContentLoaded', () => {
     const modal = document.getElementById('videoModal');
     if (modal) {
          modal.addEventListener('click', (e) => {
               if (e.target === modal) {
                    closeVideoModal();
               }
          });
     }
});