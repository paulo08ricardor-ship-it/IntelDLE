/**
 * Gerenciador Interativo da Área Administrativa - Inteldle
 */

function obterCsrfTokenGlobal() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta && meta.content) {
    return meta.content;
  }
  const input = document.querySelector('input[name="csrf_token"]');
  if (input && input.value) {
    return input.value;
  }
  return '';
}

function mostrarToast(mensagem, tipo = 'sucesso') {
  let toast = document.getElementById('adminToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'adminToast';
    document.body.appendChild(toast);
  }

  toast.className = tipo === 'erro' ? 'erro' : 'sucesso';
  toast.innerText = mensagem;
  toast.style.display = 'block';

  const tempoExibicao = tipo === 'erro' ? 6000 : 3500;
  if (window.adminToastTimer) {
    clearTimeout(window.adminToastTimer);
  }
  window.adminToastTimer = setTimeout(() => {
    toast.style.display = 'none';
  }, tempoExibicao);
}

function abrirModalAdmin(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.style.display = 'flex';
  }
}

function fecharModalAdmin(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.style.display = 'none';
  }
}

// Fechamento ao clicar fora do modal
window.addEventListener('click', (e) => {
  if (e.target && e.target.classList.contains('modal-admin-overlay')) {
    e.target.style.display = 'none';
  }
});

/**
 * Submete formulário genérico de CRUD para a respectiva API com proteção CSRF
 */
async function salvarRegistro(apiUrl, formId, modalId) {
  const form = document.getElementById(formId);
  if (!form) return;

  const dados = new FormData(form);
  dados.append('acao', 'salvar');

  const tokenCsrf = obterCsrfTokenGlobal();
  if (tokenCsrf && !dados.has('csrf_token')) {
    dados.append('csrf_token', tokenCsrf);
  }

  try {
    const resposta = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': tokenCsrf
      },
      body: dados
    });

    const json = await resposta.json();

    if (json.sucesso) {
      mostrarToast(json.mensagem || 'Operação realizada com sucesso!');
      fecharModalAdmin(modalId);
      setTimeout(() => {
        location.reload();
      }, 700);
    } else {
      mostrarToast(json.erro || 'Falha ao salvar o registro.', 'erro');
    }
  } catch (e) {
    console.error('Erro na requisição:', e);
    mostrarToast('Falha na comunicação com o servidor.', 'erro');
  }
}

/**
 * Exclui registro após confirmação com proteção CSRF
 */
async function excluirRegistro(apiUrl, id, descricaoItem) {
  if (!confirm(`Tem certeza de que deseja excluir permanentemente "${descricaoItem}"?`)) {
    return;
  }

  const tokenCsrf = obterCsrfTokenGlobal();
  const dados = new FormData();
  dados.append('acao', 'excluir');
  dados.append('id', id);
  if (tokenCsrf) {
    dados.append('csrf_token', tokenCsrf);
  }

  try {
    const resposta = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': tokenCsrf
      },
      body: dados
    });

    const json = await resposta.json();

    if (json.sucesso) {
      mostrarToast(json.mensagem || 'Excluído com sucesso!');
      setTimeout(() => {
        location.reload();
      }, 600);
    } else {
      mostrarToast(json.erro || 'Não foi possível excluir o item.', 'erro');
    }
  } catch (e) {
    console.error('Erro na exclusão:', e);
    mostrarToast('Falha de conexão com o servidor.', 'erro');
  }
}
