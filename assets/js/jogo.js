/**
 * Motor JavaScript do Jogo - Inteldle
 * Gerencia a lógica do simulador, segurança de energia, modos Diário e Infinito,
 * pontuação, sequência (streak), sistema de vidas, Game Over, bandejas e modais.
 */

// Estado global do jogo
let ligado = true;
let respondendo = false;
let streakInfinito = 0;
let recordeInfinito = 0;
let tentativasDefeitoAtual = 0;
let defeitoResolvido = false;
let vidasInfinito = 3;
const VIDAS_MAXIMAS = 3;
let emGameOver = false;

let intervaloContadorDiario = null;
let timerToast = null;

/**
 * Obtém o modo atual do jogo ('diario' ou 'infinito')
 */
function obterModoAtual() {
  return (document.body.dataset.modo || 'diario').toLowerCase();
}

/**
 * Atualiza os elementos de HUD na tela (sequência, recorde e vidas)
 */
function atualizarHUD() {
  const elStreak = document.getElementById('hudStreak');
  const elRecorde = document.getElementById('hudRecorde');
  const elVidas = document.getElementById('hudVidas');

  if (elStreak) {
    elStreak.innerText = streakInfinito;
  }

  if (elRecorde) {
    elRecorde.innerText = recordeInfinito;
  }

  if (elVidas && obterModoAtual() === 'infinito') {
    let coracoes = '';
    for (let i = 0; i < VIDAS_MAXIMAS; i++) {
      coracoes += (i < vidasInfinito) ? '❤️' : '🖤';
    }
    elVidas.innerText = coracoes;
  }
}

/**
 * Reduz uma vida no modo infinito e dispara aviso ou Game Over
 */
function perderVida(motivo) {
  if (obterModoAtual() !== 'infinito') {
    mostrarAviso(motivo);
    return;
  }
  vidasInfinito = Math.max(0, vidasInfinito - 1);
  atualizarHUD();
  if (vidasInfinito <= 0) {
    emGameOver = true;
    mostrarGameOver(motivo);
  } else {
    mostrarAviso(motivo + `\n\n💔 Vidas restantes: ${vidasInfinito}/${VIDAS_MAXIMAS}`);
  }
}

/**
 * Exibe o modal de Game Over com resumo do desempenho
 */
function mostrarGameOver(motivo) {
  const modal = document.getElementById('modalGameOver');
  const elMsg = document.getElementById('gameOverMensagem');
  const elStreak = document.getElementById('gameOverStreak');
  const elRecorde = document.getElementById('gameOverRecorde');
  if (elMsg) {
    elMsg.innerText = motivo ? `${motivo}\n\nSuas vidas acabaram!` : 'Suas vidas acabaram!';
  }
  if (elStreak) {
    elStreak.innerText = streakInfinito;
  }
  if (elRecorde) {
    elRecorde.innerText = recordeInfinito;
  }
  if (modal) {
    modal.style.display = 'flex';
  }
}

/**
 * Fecha o modal de Game Over
 */
function fecharGameOver() {
  const modal = document.getElementById('modalGameOver');
  if (modal) {
    modal.style.display = 'none';
  }
}

/**
 * Reinicia uma nova partida no Modo Infinito restaurando vidas e sequência
 */
function reiniciarJogoInfinito() {
  vidasInfinito = VIDAS_MAXIMAS;
  streakInfinito = 0;
  emGameOver = false;
  defeitoResolvido = false;
  tentativasDefeitoAtual = 0;
  fecharGameOver();
  fecharStatus();
  fecharAviso();
  atualizarHUD();
  proximoDefeito();
}

/**
 * Alterna o estado de energia do computador (LIGADO / DESLIGADO)
 */
function alternarEnergia() {
  if (emGameOver) return;

  const btnPower = document.getElementById('btnPower');
  const imgOn = document.getElementById('imgOn');
  const imgOff = document.getElementById('imgOff');

  ligado = !ligado;

  if (btnPower) {
    if (ligado) {
      btnPower.classList.remove('apagando');
      btnPower.classList.add('aceso');
    } else {
      btnPower.classList.remove('aceso');
      btnPower.classList.add('apagando');
    }
  }

  if (imgOn && imgOff) {
    imgOn.style.opacity = ligado ? '1' : '0';
    imgOff.style.opacity = ligado ? '0' : '1';
  }

  console.log(ligado ? "Sistema Energizado (ON)" : "Sistema Desativado (OFF)");
}

/**
 * Abre ou fecha a bandeja de peças/ações (Hardware ou Software)
 */
function toggleBandeja(id) {
  const bandeja = document.getElementById(id);
  if (bandeja) {
    bandeja.classList.toggle('aberta');
  }
}

/**
 * Exibe o modal de aviso de segurança
 */
function mostrarAviso(mensagem) {
  const modal = document.getElementById('modalAviso');
  const texto = document.getElementById('avisoTexto');
  if (texto) {
    texto.innerText = mensagem;
  }
  if (modal) {
    modal.style.display = 'flex';
  }
}

/**
 * Fecha o modal de aviso de segurança
 */
function fecharAviso() {
  const modal = document.getElementById('modalAviso');
  if (modal) {
    modal.style.display = 'none';
  }
}

/**
 * Exibe o modal de status (Correto / Incorreto) com opções de ação
 */
function mostrarStatus(tipo, mensagem) {
  const modal = document.getElementById('modalStatus');
  const content = document.getElementById('statusContent');
  const titulo = document.getElementById('statusTitulo');
  const texto = document.getElementById('statusMensagem');
  const btnProx = document.getElementById('btnProximoDefeito');
  const btnOk = document.getElementById('btnStatusOk');
  const acoes = document.getElementById('statusAcoes');

  if (!modal || !content) return;

  const ehCorreto = tipo === 'correto';
  const modo = obterModoAtual();

  content.classList.remove('status-ok', 'status-erro');
  content.classList.add(ehCorreto ? 'status-ok' : 'status-erro');

  if (titulo) {
    titulo.innerText = ehCorreto ? 'CORRETO' : 'INCORRETO';
  }

  if (texto) {
    texto.innerText = mensagem || '';
  }

  if (acoes) {
    // Remove botões extras de vitórias anteriores se houver
    acoes.querySelectorAll('.btn-modal-extra').forEach(el => el.remove());

    if (ehCorreto && modo === 'infinito') {
      if (btnProx) btnProx.style.display = 'inline-block';
      if (btnOk) btnOk.style.display = 'none';
    } else if (ehCorreto && modo === 'diario') {
      if (btnProx) btnProx.style.display = 'none';
      if (btnOk) {
        btnOk.style.display = 'inline-block';
        btnOk.innerText = 'VER RESUMO 📊';
        btnOk.onclick = function () {
          window.location.reload();
        };
      }

      // Adiciona botão de atalho para Modo Infinito
      const btnInfinito = document.createElement('a');
      btnInfinito.href = 'infinito.php';
      btnInfinito.className = 'btn-proximo-defeito btn-ok btn-modal-extra';
      btnInfinito.innerText = 'MODO INFINITO 🚀';
      btnInfinito.style.textDecoration = 'none';
      btnInfinito.style.display = 'inline-block';
      acoes.appendChild(btnInfinito);
    } else {
      if (btnProx) btnProx.style.display = 'none';
      if (btnOk) {
        btnOk.style.display = 'inline-block';
        btnOk.innerText = 'ENTENDIDO';
        btnOk.onclick = fecharStatus;
      }
    }
  }

  modal.style.display = 'flex';
}

/**
 * Fecha o modal de status
 */
function fecharStatus() {
  const modal = document.getElementById('modalStatus');
  if (modal) {
    modal.style.display = 'none';
  }
}

/**
 * Marca o botão clicado como usado na interface
 */
function marcarBotaoUsado(elemento) {
  if (!elemento) return;
  const botao = (typeof elemento.closest === 'function') ? (elemento.closest('.btn-vermelho') || elemento) : elemento;
  if (botao && botao.classList) {
    botao.classList.add('usado');
  }
}

/**
 * Ação disparada ao clicar em uma peça de Hardware
 * Suporta assinatura moderna (componenteId, nomePeca, elemento) e legado (nomePeca, elemento)
 */
function acaoHardware(componenteId, nomePeca, elemento) {
  if (emGameOver || defeitoResolvido) return;

  // Validação de segurança: Hardware exige computador desligado
  if (ligado) {
    if (obterModoAtual() === 'infinito') {
      perderVida("⚡ PERIGO: Você tentou mexer no hardware com o computador LIGADO! Você levou um choque.");
    } else {
      mostrarAviso("PERIGO: O computador deve ser DESLIGADO antes de manipular o hardware!");
    }
    return;
  }

  let id = componenteId;
  let nome = nomePeca;
  let el = elemento;

  // Compatibilidade com chamada legada acaoHardware(nomePeca, elemento)
  if (typeof id === 'string' && isNaN(Number(id))) {
    el = (typeof nome === 'object' && nome !== null) ? nome : el;
    nome = id;
    id = null;
  }

  el = el || (typeof window.event !== 'undefined' ? (window.event.currentTarget || window.event.target) : null);
  marcarBotaoUsado(el);

  const componenteLimpo = (nome || '').toString().trim();
  responder(id, componenteLimpo);
}

/**
 * Ação disparada ao clicar em uma ação de Software
 * Suporta assinatura moderna (componenteId, nomeSoft, elemento) e legado (nomeSoft, elemento)
 */
function acaoSoftware(componenteId, nomeSoft, elemento) {
  if (emGameOver || defeitoResolvido) return;

  // Validação de segurança: Software exige computador ligado
  if (!ligado) {
    if (obterModoAtual() === 'infinito') {
      perderVida("💻 ERRO: Você tentou acessar o software com o computador DESLIGADO! O sistema precisa estar ligado.");
    } else {
      mostrarAviso("ERRO: O computador precisa estar LIGADO para acessar o software!");
    }
    return;
  }

  let id = componenteId;
  let nome = nomeSoft;
  let el = elemento;

  // Compatibilidade com chamada legada acaoSoftware(nomeSoft, elemento)
  if (typeof id === 'string' && isNaN(Number(id))) {
    el = (typeof nome === 'object' && nome !== null) ? nome : el;
    nome = id;
    id = null;
  }

  el = el || (typeof window.event !== 'undefined' ? (window.event.currentTarget || window.event.target) : null);
  marcarBotaoUsado(el);

  const componenteLimpo = (nome || '').toString().trim();
  responder(id, componenteLimpo);
}

/**
 * Envia a resposta do jogador para validação no backend
 * Suporta responder(componenteId, componenteNome) e responder(componente)
 */
async function responder(componenteId, componenteNome) {
  if (respondendo || defeitoResolvido || emGameOver) return;

  const rawDefeitoId = document.body && document.body.dataset ? document.body.dataset.defeito : '';
  const defeitoId = (rawDefeitoId || '').toString().trim();

  if (!defeitoId) {
    console.warn("Nenhum ID de defeito encontrado na página.");
    mostrarAviso("Identificador do defeito não encontrado. Atualize a página.");
    return;
  }

  const compIdNum = (typeof componenteId === 'number' || (typeof componenteId === 'string' && /^\d+$/.test(componenteId)))
    ? parseInt(componenteId, 10)
    : null;
  const compNomeStr = (componenteNome || (typeof componenteId === 'string' && !/^\d+$/.test(componenteId) ? componenteId : '')).toString().trim();

  if (!compIdNum && !compNomeStr) {
    console.warn("Nenhum componente informado.");
    return;
  }

  const modo = obterModoAtual();
  respondendo = true;

  try {
    const dados = new FormData();
    dados.append("defeito", defeitoId);
    if (compIdNum !== null && compIdNum > 0) {
      dados.append("componente_id", compIdNum);
    }
    dados.append("componente", compNomeStr || (compIdNum !== null ? String(compIdNum) : ''));
    dados.append("modo", modo);

    const resposta = await fetch("../api/verificar.php", {
      method: "POST",
      body: dados
    });

    // Leitura segura do JSON antes de checar status HTTP
    let json = null;
    try {
      json = await resposta.json();
    } catch (parseErro) {
      console.warn("Falha ao interpretar JSON do servidor:", parseErro);
    }

    if (json) {
      // 1. Caso especial: Jogador já completou o desafio diário hoje (bloqueado)
      if (json.resultado === 'bloqueado') {
        mostrarAviso(json.mensagem || "Você já concluiu o desafio diário de hoje! Volte amanhã para um novo desafio.");
        return;
      }

      // 2. Resposta Correta (Vitória no defeito)
      if (json.resultado === 'correto') {
        defeitoResolvido = true;

        if (modo === 'infinito') {
          streakInfinito++;

          let novoRecorde = false;
          if (streakInfinito > recordeInfinito) {
            recordeInfinito = streakInfinito;
            novoRecorde = true;
          }
          atualizarHUD();

          // Salva pontos/streak de forma isolada sem bloquear o feedback de vitória
          try {
            await salvarPontosServidor('infinito', streakInfinito);
          } catch (salvarErr) {
            console.warn("Aviso: falha secundária ao registrar pontos no modo infinito:", salvarErr);
          }

          let msgSucesso = (json.mensagem || 'Defeito solucionado com sucesso!') +
            `\n\n🔥 Sequência Atual: ${streakInfinito} ${streakInfinito === 1 ? 'acerto' : 'acertos'}!`;

          if (novoRecorde && streakInfinito > 1) {
            msgSucesso += '\n🏆 NOVO RECORDE PESSOAL!';
          }

          mostrarStatus('correto', msgSucesso);
        } else {
          // Modo Diário: Salva pontuação diária isolada de falhas secundárias
          try {
            await salvarPontosServidor('diario');
          } catch (salvarErr) {
            console.warn("Aviso: falha secundária ao registrar pontuação diária:", salvarErr);
          }

          // Salva a data de conclusão de hoje no localStorage (YYYY-MM-DD)
          try {
            const hojeIso = new Date().toISOString().split('T')[0];
            localStorage.setItem('inteldle_diario_data', hojeIso);
            if (json.pontos !== undefined) {
              localStorage.setItem('inteldle_diario_pontos', json.pontos);
            }
          } catch (e) {
            console.warn('Erro ao salvar conclusão diária no localStorage:', e);
          }

          const msgDiario = (json.mensagem || 'Defeito solucionado com sucesso!') +
            `\n\n🎉 Pontuação Obtida: ${(json.pontos || 10000).toLocaleString('pt-BR')} pts!` +
            '\n\nSeu resultado diário foi registrado no ranking!';

          mostrarStatus('correto', msgDiario);
        }
        return;
      }

      // 3. Resposta Incorreta ou Falha de Validação retornada pelo servidor
      if (modo === 'infinito') {
        const streakAnterior = streakInfinito;
        tentativasDefeitoAtual++;
        streakInfinito = 0;
        atualizarHUD();

        let msgErro = json.mensagem || 'Componente incorreto.';
        if (streakAnterior > 0) {
          msgErro += `\n\n❌ Sequência de ${streakAnterior} ${streakAnterior === 1 ? 'acerto' : 'acertos'} zerada!`;
        }

        perderVida(msgErro);
      } else {
        // Se a resposta for erro no modo diário
        mostrarStatus('erro', json.mensagem || 'Componente incorreto.');
      }
      return;
    }

    // Se não retornou JSON e a resposta HTTP não foi bem-sucedida
    if (!resposta.ok) {
      throw new Error(`Erro na requisição: HTTP ${resposta.status}`);
    }

    throw new Error("Resposta inválida recebida do servidor.");
  } catch (erro) {
    console.error("Falha ao verificar resposta:", erro);
    mostrarAviso("Ocorreu um erro ao comunicar com o servidor. Tente novamente.");
  } finally {
    respondendo = false;
  }
}

/**
 * Salva a pontuação no backend via api/salvar_pontos.php de forma resiliente
 */
async function salvarPontosServidor(modo, pontos) {
  try {
    const dados = new FormData();
    dados.append('modo', modo || obterModoAtual());
    if (pontos !== undefined && pontos !== null) {
      dados.append('pontos', pontos);
    }

    const resposta = await fetch("../api/salvar_pontos.php", {
      method: "POST",
      body: dados
    });

    let json = null;
    try {
      json = await resposta.json();
    } catch (e) {
      // Ignora erro de JSON malformado
    }

    if (!resposta.ok) {
      console.warn(`[salvarPontosServidor] Servidor retornou HTTP ${resposta.status}:`, json);
      return json || { sucesso: false, status: resposta.status };
    }

    return json || { sucesso: true };
  } catch (erro) {
    console.warn("Aviso ao salvar pontuação:", erro);
    return { sucesso: false, erro: (erro && erro.message) ? erro.message : String(erro) };
  }
}

/**
 * Carrega um novo desafio no modo infinito via api/buscar_defeito.php
 */
async function proximoDefeito() {
  if (emGameOver) return;

  fecharStatus();
  fecharAviso();

  const currentId = document.body.dataset.defeito || '';
  try {
    const resposta = await fetch(`../api/buscar_defeito.php?exclude_id=${encodeURIComponent(currentId)}`);
    if (!resposta.ok) {
      throw new Error(`HTTP ${resposta.status}`);
    }

    const novoDefeito = await resposta.json();

    if (!novoDefeito || !novoDefeito.id) {
      console.warn("Nenhum defeito retornado pela API.");
      return;
    }

    // Atualiza o ID do defeito ativo
    document.body.dataset.defeito = novoDefeito.id;

    // Atualiza os textos e a imagem do simulador
    const elTitulo = document.getElementById('tituloDefeito');
    const elDesc = document.getElementById('textoStatusSistema');
    const elImg = document.getElementById('imgComputador');

    if (elTitulo) {
      elTitulo.innerText = novoDefeito.titulo || 'Defeito do Sistema';
    }

    if (elDesc) {
      elDesc.innerText = novoDefeito.descricao || '';
    }

    if (elImg) {
      const nomeImg = novoDefeito.imagem ? novoDefeito.imagem : 'monitor.png';
      elImg.src = `../assets/imagens/${nomeImg}`;
    }

    // Limpa a marcação de botões usados
    document.querySelectorAll('.btn-vermelho.usado').forEach(btn => {
      btn.classList.remove('usado');
    });

    // Fecha bandejas abertas
    document.querySelectorAll('.linha-pecas.aberta').forEach(bandeja => {
      bandeja.classList.remove('aberta');
    });

    // Redefine a energia para LIGADO (padrão de início)
    ligado = true;
    const btnPower = document.getElementById('btnPower');
    const imgOn = document.getElementById('imgOn');
    const imgOff = document.getElementById('imgOff');

    if (btnPower) {
      btnPower.classList.remove('apagando');
      btnPower.classList.add('aceso');
    }
    if (imgOn) imgOn.style.opacity = '1';
    if (imgOff) imgOff.style.opacity = '0';

    // Reinicia o estado de resolução do novo defeito (mantendo vidas)
    tentativasDefeitoAtual = 0;
    defeitoResolvido = false;

  } catch (erro) {
    console.error("Falha ao carregar próximo defeito:", erro);
    mostrarAviso("Não foi possível carregar o próximo desafio. Tente novamente.");
  }
}

/**
 * Inicia o relógio de contagem regressiva para a próxima meia-noite (00:00:00).
 */
function iniciarContadorRegressivoDiario() {
  const container = document.getElementById('contadorRegressivoDiario');
  if (!container) return;

  function atualizarTempo() {
    const agora = new Date();
    const amanha = new Date(
      agora.getFullYear(),
      agora.getMonth(),
      agora.getDate() + 1,
      0, 0, 0, 0
    );

    const diferencaMs = amanha.getTime() - agora.getTime();
    if (diferencaMs <= 0) {
      const elH = document.getElementById('cdHoras');
      const elM = document.getElementById('cdMinutos');
      const elS = document.getElementById('cdSegundos');
      if (elH) elH.innerText = '00';
      if (elM) elM.innerText = '00';
      if (elS) elS.innerText = '00';
      return;
    }

    const totalSegundos = Math.floor(diferencaMs / 1000);
    const horas = Math.floor(totalSegundos / 3600);
    const minutos = Math.floor((totalSegundos % 3600) / 60);
    const segundos = totalSegundos % 60;

    const strH = String(horas).padStart(2, '0');
    const strM = String(minutos).padStart(2, '0');
    const strS = String(segundos).padStart(2, '0');

    const elH = document.getElementById('cdHoras');
    const elM = document.getElementById('cdMinutos');
    const elS = document.getElementById('cdSegundos');

    if (elH && elM && elS) {
      elH.innerText = strH;
      elM.innerText = strM;
      elS.innerText = strS;
    } else {
      container.innerText = `${strH}h ${strM}m ${strS}s`;
    }
  }

  atualizarTempo();
  if (intervaloContadorDiario) {
    clearInterval(intervaloContadorDiario);
  }
  intervaloContadorDiario = setInterval(atualizarTempo, 1000);
}

/**
 * Exibe notificação Toast flutuante com feedback de ação
 */
function mostrarToast(mensagem) {
  let toast = document.getElementById('toastNotificacao');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toastNotificacao';
    toast.className = 'toast-notificacao';
    document.body.appendChild(toast);
  }

  toast.innerHTML = `<span>✓</span> <span>${mensagem}</span>`;
  toast.classList.add('mostrar');

  if (timerToast) {
    clearTimeout(timerToast);
  }

  timerToast = setTimeout(() => {
    toast.classList.remove('mostrar');
  }, 3500);
}

/**
 * Copia o resumo do resultado diário para a área de transferência
 */
async function compartilharResultadoDiario(pontos, defeito) {
  const painel = document.getElementById('painelDiarioConcluido');
  let pts = pontos;
  let def = defeito;

  if (pts === undefined || pts === null) {
    if (painel && painel.dataset.pontuacao) {
      pts = painel.dataset.pontuacao;
    } else {
      const elPontos = document.getElementById('statPontuacao');
      pts = elPontos ? elPontos.innerText.replace(/\D/g, '') : '10000';
    }
  }

  if (!def) {
    if (painel && painel.dataset.defeito) {
      def = painel.dataset.defeito;
    } else {
      const elDef = document.querySelector('.solucao-defeito-titulo') || document.getElementById('tituloDefeito');
      def = elDef ? elDef.innerText.trim() : 'Diagnóstico de Manutenção';
    }
  }

  const agora = new Date();
  const dataFormatada = `${String(agora.getDate()).padStart(2, '0')}/${String(agora.getMonth() + 1).padStart(2, '0')}/${agora.getFullYear()}`;
  const pontosFormatados = Number(pts).toLocaleString('pt-BR');

  const textoCompartilhar = [
    '⚡ Inteldle - Desafio Diário ⚡',
    `📅 Data: ${dataFormatada}`,
    `🏆 Pontuação: ${pontosFormatados} pts`,
    `🔧 Defeito: ${def}`,
    '✓ Computador diagnosticado e reparado com sucesso!',
    `👉 Jogue também: ${window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/diario.php')}`
  ].join('\n');

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(textoCompartilhar);
    } else {
      const areaTemp = document.createElement('textarea');
      areaTemp.value = textoCompartilhar;
      areaTemp.style.position = 'fixed';
      areaTemp.style.opacity = '0';
      document.body.appendChild(areaTemp);
      areaTemp.focus();
      areaTemp.select();
      document.execCommand('copy');
      document.body.removeChild(areaTemp);
    }

    mostrarToast('Resultado copiado para a área de transferência!');
  } catch (err) {
    console.error('Falha ao copiar:', err);
    mostrarToast('Não foi possível copiar automaticamente.');
  }
}

// Fechamento de modais ao clicar no fundo escuro
document.addEventListener('click', function (event) {
  if (event.target && event.target.id === 'modalStatus') {
    fecharStatus();
  }
  if (event.target && event.target.id === 'modalAviso') {
    fecharAviso();
  }
  if (event.target && event.target.id === 'modalGameOver') {
    fecharGameOver();
  }
});

// Inicialização ao carregar a página
document.addEventListener('DOMContentLoaded', function () {
  const datasetRecorde = parseInt(document.body.dataset.recorde, 10);
  if (!isNaN(datasetRecorde)) {
    recordeInfinito = datasetRecorde;
  }
  atualizarHUD();

  // Inicia contador regressivo diário se estiver presente na tela
  if (document.getElementById('contadorRegressivoDiario')) {
    iniciarContadorRegressivoDiario();
  }
});

// Exportação explícita de funções para escopo global
window.acaoHardware = acaoHardware;
window.acaoSoftware = acaoSoftware;
window.toggleBandeja = toggleBandeja;
window.alternarEnergia = alternarEnergia;
window.fecharStatus = fecharStatus;
window.fecharAviso = fecharAviso;
window.proximoDefeito = proximoDefeito;
window.mostrarStatus = mostrarStatus;
window.mostrarAviso = mostrarAviso;
window.responder = responder;
window.perderVida = perderVida;
window.mostrarGameOver = mostrarGameOver;
window.fecharGameOver = fecharGameOver;
window.reiniciarJogoInfinito = reiniciarJogoInfinito;
window.compartilharResultadoDiario = compartilharResultadoDiario;
window.iniciarContadorRegressivoDiario = iniciarContadorRegressivoDiario;
window.mostrarToast = mostrarToast;