// admin.js

$(document).ready(function () {

     // 0. Navegação em Abas (Tabs) do Painel
     $('.nav-link').on('click', function(e) {
          e.preventDefault();

          // Remove active de todos os links e adiciona no clicado
          $('.nav-link').removeClass('active');
          $(this).addClass('active');

          // Esconde todas as seções
          $('.admin-section').hide();

          // Pega o ID da seção a partir do href do link clicado e mostra ela
          let targetSection = $(this).attr('href');
          $(targetSection).fadeIn();
     });

     // 1. Salvar Configurações Gerais (Metas e PIX)
     $('#formConfiguracoes').on('submit', function (e) {
          e.preventDefault(); // Bloqueia o recarregamento da página

          let formData = $(this).serialize();

          $.ajax({
               url: 'acoes.php',
               type: 'POST',
               data: formData,
               dataType: 'json',
               success: function (response) {
                    if (response.status === 'success') {
                         Swal.fire({
                              icon: 'success',
                              title: 'Salvo!',
                              text: response.message,
                              timer: 2000,
                              showConfirmButton: false
                         });
                    } else {
                         Swal.fire('Erro!', response.message, 'error');
                    }
               },
               error: function () {
                    Swal.fire('Erro!', 'Falha ao conectar com o servidor.', 'error');
               }
          });
     });

     // 2. Adicionar Atualização no Mural
     $('#formAtualizacao').on('submit', function (e) {
          e.preventDefault();

          let formData = $(this).serialize();

          $.ajax({
               url: 'acoes.php',
               type: 'POST',
               data: formData,
               dataType: 'json',
               success: function (response) {
                    if (response.status === 'success') {
                         Swal.fire({
                              icon: 'success',
                              title: 'Publicado!',
                              text: response.message,
                              showConfirmButton: true
                         }).then(() => {
                              location.reload();
                         });
                    } else {
                         Swal.fire('Erro!', response.message, 'error');
                    }
               }
          });
     });

     // 3. Adicionar Exame / Documento (COM UPLOAD DE ARQUIVO)
     $('#formExame').on('submit', function (e) {
          e.preventDefault();

          // Usamos FormData em vez de serialize() porque estamos enviando um arquivo físico
          let formData = new FormData(this);

          $.ajax({
               url: 'acoes.php',
               type: 'POST',
               data: formData,
               dataType: 'json',
               processData: false, // Obrigatório para enviar arquivos no jQuery
               contentType: false, // Obrigatório para enviar arquivos no jQuery
               success: function (response) {
                    if (response.status === 'success') {
                         Swal.fire({
                              icon: 'success',
                              title: 'Enviado!',
                              text: response.message,
                              showConfirmButton: true
                         }).then(() => {
                              location.reload();
                         });
                    } else {
                         Swal.fire('Erro!', response.message, 'error');
                    }
               },
               error: function () {
                    Swal.fire('Erro!', 'Falha ao enviar o arquivo. Verifique se o arquivo não é muito grande.', 'error');
               }
          });
     });

     // 4. Alternar campos de imagem/vídeo na Galeria
     $('input[name="midia_tipo"]').on('change', function () {
          if ($(this).val() === 'video') {
               $('.campo-video').fadeIn();
               $('.campo-imagem').fadeOut();
          } else {
               $('.campo-imagem').fadeIn();
               $('.campo-video').fadeOut();
          }
     });

     // 5. Enviar item para a Galeria
     $('#formGaleria').on('submit', function (e) {
          e.preventDefault();
          let formData = new FormData(this);

          $.ajax({
               url: 'acoes.php',
               type: 'POST',
               data: formData,
               dataType: 'json',
               processData: false,
               contentType: false,
               success: function (response) {
                    if (response.status === 'success') {
                         Swal.fire('Adicionado!', response.message, 'success').then(() => location.reload());
                    } else {
                         Swal.fire('Erro!', response.message, 'error');
                    }
               }
          });
     });

     // Adicionar Vídeo do Médico
     $('#formVideoMedico').on('submit', function (e) {
          e.preventDefault();
          let formData = $(this).serialize();

          $.ajax({
               url: 'acoes.php',
               type: 'POST',
               data: formData,
               dataType: 'json',
               success: function (response) {
                    if (response.status === 'success') {
                         Swal.fire('Adicionado!', response.message, 'success').then(() => location.reload());
                    } else {
                         Swal.fire('Erro!', response.message, 'error');
                    }
               }
          });
     });

     // 6. Registrar Doação Manual (Atualiza Valor e Mural Simultaneamente)
     $('#formDoacaoManual').on('submit', function (e) {
          e.preventDefault();

          let formData = $(this).serialize(); // Pega o nome e o valor digitado

          $.ajax({
               url: 'acoes.php',
               type: 'POST',
               data: formData,
               dataType: 'json',
               success: function (response) {
                    if (response.status === 'success') {
                         Swal.fire({
                              icon: 'success',
                              title: 'Incrível!',
                              text: response.message,
                              showConfirmButton: true,
                              confirmButtonText: 'Ver atualização'
                         }).then(() => {
                              // Recarrega a página para atualizar os números do painel
                              location.reload();
                         });
                    } else {
                         Swal.fire('Erro!', response.message, 'error');
                    }
               },
               error: function () {
                    Swal.fire('Erro!', 'Falha ao conectar com o servidor.', 'error');
               }
          });
     });

}); // ======== FIM DO DOCUMENT.READY ========


// ==========================================
// FUNÇÕES DE EXCLUSÃO (Globais - Fora do document.ready)
// ==========================================

function excluirAtualizacao(id) {
     Swal.fire({
          title: 'Tem certeza?',
          text: "Esta atualização sumirá do site!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#EF4444',
          cancelButtonColor: '#334155',
          confirmButtonText: 'Sim, excluir!',
          cancelButtonText: 'Cancelar'
     }).then((result) => {
          if (result.isConfirmed) {
               $.ajax({
                    url: 'acoes.php',
                    type: 'POST',
                    data: { acao: 'excluir_atualizacao', id: id },
                    dataType: 'json',
                    success: function (response) {
                         if (response.status === 'success') {
                              $('#linha-' + id).fadeOut(400, function () { $(this).remove(); });
                              Swal.fire('Excluído!', 'Atualização removida.', 'success');
                         } else {
                              Swal.fire('Erro!', 'Não foi possível excluir.', 'error');
                         }
                    }
               });
          }
     });
}

function excluirVideoMedico(id) {
     Swal.fire({
          title: 'Excluir vídeo do médico?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#EF4444',
          confirmButtonText: 'Sim'
     }).then((result) => {
          if (result.isConfirmed) {
               $.post('acoes.php', { acao: 'excluir_video_medico', id: id }, function (res) {
                    if (res.status === 'success') {
                         $('#video-medico-' + id).fadeOut();
                    }
               }, 'json');
          }
     });
}

function excluirDoc(id) {
     Swal.fire({
          title: 'Tem certeza?',
          text: "O documento será apagado permanentemente do servidor!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#EF4444',
          cancelButtonColor: '#334155',
          confirmButtonText: 'Sim, excluir!',
          cancelButtonText: 'Cancelar'
     }).then((result) => {
          if (result.isConfirmed) {
               $.ajax({
                    url: 'acoes.php',
                    type: 'POST',
                    data: { acao: 'excluir_documento', id: id },
                    dataType: 'json',
                    success: function (response) {
                         if (response.status === 'success') {
                              $('#doc-' + id).fadeOut(400, function () { $(this).remove(); });
                              Swal.fire('Excluído!', 'O documento foi apagado.', 'success');
                         } else {
                              Swal.fire('Erro!', 'Não foi possível excluir.', 'error');
                         }
                    }
               });
          }
     });
}

function excluirGaleria(id) {
     Swal.fire({
          title: 'Excluir mídia?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#EF4444',
          confirmButtonText: 'Sim'
     }).then((result) => {
          if (result.isConfirmed) {
               $.post('acoes.php', { acao: 'excluir_galeria', id: id }, function (res) {
                    if (res.status === 'success') {
                         $('#gal-' + id).fadeOut();
                    }
               }, 'json');
          }
     });
}