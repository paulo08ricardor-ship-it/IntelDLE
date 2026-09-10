// Script unificado de gerenciamento e alternância de tema Claro / Escuro - Inteldle

function getCookieTema() {
  const match = document.cookie.match(/(^|;)\s*inteldle_tema\s*=\s*([^;]+)/);
  return match ? decodeURIComponent(match[2]) : null;
}

function obterTemaPreferido() {
  const local = localStorage.getItem('tema');
  if (local === 'claro' || local === 'escuro') return local;
  
  const cookie = getCookieTema();
  if (cookie === 'claro' || cookie === 'escuro') return cookie;

  if (document.body && document.body.classList.contains('tema-claro')) return 'claro';
  if (document.documentElement && document.documentElement.classList.contains('tema-claro')) return 'claro';
  
  return 'escuro';
}

function aplicarTema(tema) {
  const ehClaro = (tema === 'claro');
  
  if (ehClaro) {
    document.documentElement.classList.add('tema-claro');
    if (document.body) document.body.classList.add('tema-claro');
  } else {
    document.documentElement.classList.remove('tema-claro');
    if (document.body) document.body.classList.remove('tema-claro');
  }

  // Atualiza botões de seleção de tema no modal de opções caso existam
  const btnEscuro = document.getElementById('btnTemaEscuro');
  const btnClaro = document.getElementById('btnTemaClaro');
  if (btnClaro && btnEscuro) {
    if (ehClaro) {
      btnClaro.classList.add('selecionado');
      btnEscuro.classList.remove('selecionado');
    } else {
      btnEscuro.classList.add('selecionado');
      btnClaro.classList.remove('selecionado');
    }
  }
}

async function alterarTema(tema) {
  aplicarTema(tema);

  localStorage.setItem('tema', tema);
  document.cookie = "inteldle_tema=" + encodeURIComponent(tema) + "; path=/; max-age=31536000; SameSite=Lax";

  try {
    const dados = new FormData();
    dados.append('tema', tema);
    await fetch('../api/salvar_tema.php', {
      method: 'POST',
      body: dados
    });
  } catch (e) {
    console.warn('Falha ao sincronizar tema com o servidor:', e);
  }
}

function toggleTemaGlobal() {
  const ehClaroAgora = document.documentElement.classList.contains('tema-claro') || (document.body && document.body.classList.contains('tema-claro'));
  const novoTema = ehClaroAgora ? 'escuro' : 'claro';
  alterarTema(novoTema);
}

// Aplicação instantânea contra flicker
(function() {
  const t = obterTemaPreferido();
  if (t === 'claro') {
    document.documentElement.classList.add('tema-claro');
  } else {
    document.documentElement.classList.remove('tema-claro');
  }
})();

// Reafirma a aplicação quando a árvore DOM estiver pronta
document.addEventListener('DOMContentLoaded', () => {
  aplicarTema(obterTemaPreferido());
});

window.toggleTemaGlobal = toggleTemaGlobal;
window.alterarTema = alterarTema;
window.aplicarTema = aplicarTema;
