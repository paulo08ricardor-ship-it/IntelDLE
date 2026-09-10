/**
 * Central de Diagnóstico e Testes - Inteldle
 * Gerencia a abertura e fechamento do modal de diagnóstico, navegação por etapas
 * (Categorias ⇄ Lista de Testes), consumo assíncrono da API de testes com defeito_id ativo,
 * renderização dinâmica de cards, exibição de resultados técnicos e terminal de telemetria.
 */

// Estado interno do módulo de diagnóstico
const DiagnosticoState = {
  aberto: false,
  etapa: 1, // 1 = Categorias, 2 = Testes
  categoriaAtiva: null,
  categoriaNome: '',
  testesEmExecucao: new Set(),
  cacheTestes: {},
  mapaTestes: new Map()
};

/**
 * Obtém o identificador do defeito ativo no jogo a partir do DOM ou estado global
 * @returns {string} ID do defeito ativo ou string vazia
 */
function obterDefeitoAtivoId() {
  const elCorpo = document.body;
  if (elCorpo && elCorpo.dataset && elCorpo.dataset.defeito) {
    return elCorpo.dataset.defeito;
  }
  if (typeof window.defeitoAtualId !== 'undefined' && window.defeitoAtualId !== null) {
    return String(window.defeitoAtualId);
  }
  return '';
}

/**
 * Abre o modal da Central de Diagnóstico
 */
function abrirModalDiagnostico() {
  const modal = document.getElementById('modalDiagnostico');
  if (!modal) {
    console.warn('[Diagnostico] Elemento #modalDiagnostico não encontrado no DOM.');
    return;
  }

  DiagnosticoState.aberto = true;
  modal.style.display = 'flex';

  // Animação suave via requestAnimationFrame
  requestAnimationFrame(() => {
    modal.classList.add('aberto');
  });

  // Sempre reinicia na Etapa 1 ao abrir
  mostrarEtapaDiagnostico(1);

  // Registra listener da tecla Escape
  document.addEventListener('keydown', lidarTeclaEscapeDiagnostico);

  const defeitoId = obterDefeitoAtivoId();
  adicionarLogTerminal(`[SISTEMA] Central de Diagnóstico inicializada. Defeito alvo ID: #${defeitoId || 'AUTOMÁTICO'}.`, 'info');
}

/**
 * Fecha o modal da Central de Diagnóstico
 */
function fecharModalDiagnostico() {
  const modal = document.getElementById('modalDiagnostico');
  if (!modal) return;

  DiagnosticoState.aberto = false;
  modal.classList.remove('aberto');

  setTimeout(() => {
    if (!DiagnosticoState.aberto) {
      modal.style.display = 'none';
    }
  }, 250);

  // Remove listener da tecla Escape
  document.removeEventListener('keydown', lidarTeclaEscapeDiagnostico);
}

/**
 * Trata o fechamento ao pressionar a tecla Escape
 */
function lidarTeclaEscapeDiagnostico(evento) {
  if (evento.key === 'Escape' || evento.key === 'Esc') {
    fecharModalDiagnostico();
  }
}

/**
 * Alterna visualmente entre a Etapa 1 (Categorias) e Etapa 2 (Lista de Testes)
 */
function mostrarEtapaDiagnostico(etapa) {
  DiagnosticoState.etapa = etapa;
  const etapa1 = document.getElementById('diagnosticoEtapa1');
  const etapa2 = document.getElementById('diagnosticoEtapa2');

  if (!etapa1 || !etapa2) return;

  if (etapa === 1) {
    etapa1.style.display = 'block';
    etapa2.style.display = 'none';
    etapa1.classList.add('diagnostico-etapa-ativa');
    etapa2.classList.remove('diagnostico-etapa-ativa');
  } else {
    etapa1.style.display = 'none';
    etapa2.style.display = 'block';
    etapa2.classList.add('diagnostico-etapa-ativa');
    etapa1.classList.remove('diagnostico-etapa-ativa');
  }
}

/**
 * Seleciona uma categoria na Etapa 1 e carrega seus testes dinâmicos
 * @param {string} tipo Identificador do tipo (hardware, software, rede, seguranca, geral)
 * @param {string} nomeLegivel Título amigável da categoria
 */
function selecionarCategoriaDiagnostico(tipo, nomeLegivel) {
  const catTipo = (tipo || 'hardware').toLowerCase();
  DiagnosticoState.categoriaAtiva = catTipo;
  DiagnosticoState.categoriaNome = nomeLegivel || obterTituloCategoriaPadrao(catTipo);

  const elTitulo = document.getElementById('diagCategoriaTitulo');
  if (elTitulo) {
    elTitulo.textContent = DiagnosticoState.categoriaNome;
  }

  mostrarEtapaDiagnostico(2);
  adicionarLogTerminal(`[VARREDURA] Subsistema selecionado: ${DiagnosticoState.categoriaNome.toUpperCase()}. Sondas ativadas.`, 'info');
  carregarTestes(catTipo);
}

/**
 * Retorna o título padrão de cada categoria
 * @param {string} tipo Identificador do tipo
 * @returns {string} Título amigável
 */
function obterTituloCategoriaPadrao(tipo) {
  switch (tipo) {
    case 'hardware':
      return 'Hardware e Componentes Físicos';
    case 'software':
      return 'Software e Sistema Operacional';
    case 'rede':
      return 'Rede e Conectividade';
    case 'seguranca':
      return 'Segurança e Ameaças';
    case 'geral':
      return 'Varredura Geral do Sistema';
    default:
      return 'Diagnóstico do Sistema';
  }
}

/**
 * Retorna da Etapa 2 para a Etapa 1 (Seleção de Categorias)
 */
function voltarParaCategorias() {
  mostrarEtapaDiagnostico(1);
  adicionarLogTerminal('[SISTEMA] Retornando à matriz de seleção de subsistemas.', 'info');
}

/**
 * Carrega a lista de testes para uma categoria via Fetch API com o defeito ativo
 * @param {string} tipo Categoria a ser buscada (hardware, software, rede, seguranca, geral)
 */
async function carregarTestes(tipo) {
  const containerLista = document.getElementById('diagListaTestes');
  const containerSpinner = document.getElementById('diagLoadingSpinner');
  const containerVazio = document.getElementById('diagMensagemVazia');
  const textoVazio = document.getElementById('diagTextoVazio');

  if (!containerLista) return;

  // Estado de carregamento inicial
  containerLista.innerHTML = '';
  if (containerVazio) containerVazio.style.display = 'none';
  if (containerSpinner) containerSpinner.style.display = 'flex';

  const defeitoId = obterDefeitoAtivoId();

  try {
    const url = `../api/buscar_testes.php?tipo=${encodeURIComponent(tipo)}&defeito_id=${encodeURIComponent(defeitoId)}`;
    const resposta = await fetch(url, {
      headers: {
        'Accept': 'application/json'
      }
    });

    if (!resposta.ok) {
      throw new Error(`Servidor respondeu com status HTTP ${resposta.status}`);
    }

    const dados = await resposta.json();
    let listaTestes = [];

    // Suporta múltiplos formatos de resposta da API
    if (Array.isArray(dados)) {
      listaTestes = dados;
    } else if (dados && Array.isArray(dados.dados)) {
      listaTestes = dados.dados;
    } else if (dados && Array.isArray(dados.testes)) {
      listaTestes = dados.testes;
    } else if (dados && Array.isArray(dados.resultado)) {
      listaTestes = dados.resultado;
    }

    if (containerSpinner) containerSpinner.style.display = 'none';

    if (listaTestes.length === 0) {
      // Fallback gracioso com testes técnicos enriquecidos para a categoria selecionada
      listaTestes = gerarTestesFallback(tipo, defeitoId);
    }

    // Armazena os testes no mapa interno para acesso rápido durante a execução
    listaTestes.forEach((t, idx) => {
      const chaveId = t.id !== undefined ? String(t.id) : `fb_${tipo}_${idx}`;
      t.id = chaveId;
      DiagnosticoState.mapaTestes.set(chaveId, t);
    });

    renderizarCardsTestes(listaTestes, tipo);
    adicionarLogTerminal(`[OK] ${listaTestes.length} rotinas de teste carregadas para ${DiagnosticoState.categoriaNome}.`, 'sucesso');

  } catch (erro) {
    console.warn('[Diagnostico] Falha na requisição da API de testes. Ativando telemetria local:', erro);
    if (containerSpinner) containerSpinner.style.display = 'none';

    // Se houver falha de rede/API, carrega os testes enriquecidos simulados com base no defeito ativo
    const testesFallback = gerarTestesFallback(tipo, defeitoId);
    testesFallback.forEach((t, idx) => {
      const chaveId = t.id !== undefined ? String(t.id) : `fb_${tipo}_${idx}`;
      t.id = chaveId;
      DiagnosticoState.mapaTestes.set(chaveId, t);
    });

    renderizarCardsTestes(testesFallback, tipo);
    adicionarLogTerminal(`[TELEMETRIA LOCAL] ${testesFallback.length} rotinas ativas com análise contextual do defeito #${defeitoId || '1'}.`, 'destaque');
  }
}

/**
 * Renderiza dinamicamente os cards de testes na interface com segurança (anti-XSS)
 * @param {Array} testes Lista de objetos de testes
 * @param {string} tipo Categoria atual
 */
function renderizarCardsTestes(testes, tipo) {
  const containerLista = document.getElementById('diagListaTestes');
  const containerVazio = document.getElementById('diagMensagemVazia');
  const textoVazio = document.getElementById('diagTextoVazio');

  if (!containerLista) return;
  containerLista.innerHTML = '';

  if (!testes || testes.length === 0) {
    if (containerVazio) {
      if (textoVazio) {
        textoVazio.textContent = `Nenhum teste disponível para a categoria '${tipo}' no momento.`;
      }
      containerVazio.style.display = 'flex';
    }
    return;
  }

  if (containerVazio) containerVazio.style.display = 'none';

  testes.forEach((teste, index) => {
    const card = document.createElement('div');
    card.className = 'card-teste-item';
    card.id = `cardTeste_${teste.id}`;

    // Ícone por subsistema
    const icone = teste.icone_emoji || obterIconePorTipo(teste.tipo || tipo);

    // 1. Cabeçalho do Card
    const cardHeader = document.createElement('div');
    cardHeader.className = 'card-teste-header';

    const cardTituloBox = document.createElement('div');
    cardTituloBox.className = 'card-teste-titulo-box';

    const spanIcone = document.createElement('span');
    spanIcone.className = 'card-teste-icone';
    spanIcone.textContent = icone;

    const spanNome = document.createElement('h4');
    spanNome.className = 'card-teste-nome';
    spanNome.textContent = teste.nome || teste.titulo || `Teste de ${tipo.toUpperCase()}`;

    cardTituloBox.appendChild(spanIcone);
    cardTituloBox.appendChild(spanNome);

    const badgeStatus = document.createElement('span');
    badgeStatus.className = 'badge-teste-status status-pronto';
    badgeStatus.id = `badgeStatus_${teste.id}`;
    badgeStatus.textContent = 'PRONTO';

    cardHeader.appendChild(cardTituloBox);
    cardHeader.appendChild(badgeStatus);

    // 2. Descrição do Teste
    const cardDesc = document.createElement('p');
    cardDesc.className = 'card-teste-desc';
    cardDesc.textContent = teste.descricao || 'Executa varredura de integridade e medição dos parâmetros operacionais.';

    // 3. Container da Caixa de Resultado Técnico (Inicialmente vazia/oculta)
    const caixaResultado = document.createElement('div');
    caixaResultado.className = 'caixa-resultado-teste';
    caixaResultado.id = `caixaResultado_${teste.id}`;
    caixaResultado.style.display = 'none';

    // 4. Rodapé com Ações e Duração
    const cardFooter = document.createElement('div');
    cardFooter.className = 'card-teste-footer';

    const spanDica = document.createElement('span');
    spanDica.className = 'card-teste-tempo';
    spanDica.textContent = `⏱ Duração: ${teste.tempo_estimado || '~1.5s'}`;

    const btnExecutar = document.createElement('button');
    btnExecutar.type = 'button';
    btnExecutar.className = 'btn-executar-teste';
    btnExecutar.id = `btnTeste_${teste.id}`;
    btnExecutar.innerHTML = '<span class="btn-icone">▶</span> EXECUTAR TESTE';
    btnExecutar.onclick = function () {
      executarTesteIndividual(teste.id, teste.nome || 'Teste', btnExecutar, teste);
    };

    cardFooter.appendChild(spanDica);
    cardFooter.appendChild(btnExecutar);

    // Monta a estrutura completa do card
    card.appendChild(cardHeader);
    card.appendChild(cardDesc);
    card.appendChild(caixaResultado);
    card.appendChild(cardFooter);

    containerLista.appendChild(card);
  });
}

/**
 * Executa um teste individual com feedback visual, telemetria em tempo real e análise técnica
 * @param {string|number} id Identificador do teste
 * @param {string} nome Nome do teste
 * @param {HTMLElement} btn Elemento do botão disparador
 * @param {Object} teste Objeto completo do teste
 */
function executarTesteIndividual(id, nome, btn, teste) {
  if (DiagnosticoState.testesEmExecucao.has(id)) {
    return; // Previne múltiplas execuções concorrentes do mesmo teste
  }

  DiagnosticoState.testesEmExecucao.add(id);

  const card = document.getElementById(`cardTeste_${id}`);
  const badge = document.getElementById(`badgeStatus_${id}`);
  const caixaResultado = document.getElementById(`caixaResultado_${id}`);

  // Recupera os dados mais atualizados do teste armazenados no estado
  const dadosTeste = DiagnosticoState.mapaTestes.get(String(id)) || teste;
  const defeitoId = obterDefeitoAtivoId();

  // 1. Estado Visual: "ANALISANDO / TESTANDO..."
  if (btn) {
    btn.disabled = true;
    btn.classList.add('executando');
    btn.innerHTML = '<span class="spinner-mini"></span> TESTANDO...';
  }

  if (badge) {
    badge.className = 'badge-teste-status status-executando';
    badge.textContent = 'ANALISANDO...';
  }

  if (card) {
    card.classList.remove('card-teste-anomalia', 'card-teste-saudavel', 'card-anomalia', 'card-saudavel');
    card.classList.add('card-executando');
  }

  if (caixaResultado) {
    caixaResultado.style.display = 'none';
    caixaResultado.className = 'caixa-resultado-teste';
    caixaResultado.innerHTML = '';
  }

  // Logs imersivos no terminal
  adicionarLogTerminal(`[INICIADO] Executando rotina técnica: "${nome}"...`, 'info');

  if (dadosTeste.comando_ou_acao) {
    setTimeout(() => {
      adicionarLogTerminal(`[SONDA / COMANDO] ${dadosTeste.comando_ou_acao}`, 'info');
    }, 350);
  }

  setTimeout(() => {
    adicionarLogTerminal(`[TELEMETRIA] Coletando pacotes e medindo sensores de "${nome}"...`, 'info');
  }, 750);

  // Conclusão da análise após 1.3s
  setTimeout(() => {
    DiagnosticoState.testesEmExecucao.delete(id);

    // Obtém resultado técnico com validação de anomalia baseada no defeito ativo
    const resultadoInfo = obterResultadoTesteFinal(dadosTeste, defeitoId);

    // Atualiza Botão para Repetir
    if (btn) {
      btn.disabled = false;
      btn.classList.remove('executando');
      btn.classList.add('concluido');
      btn.innerHTML = '✓ REPETIR TESTE';
    }

    if (card) {
      card.classList.remove('card-executando');
    }

    const agora = new Date();
    const horaFormatada = `${String(agora.getHours()).padStart(2, '0')}:${String(agora.getMinutes()).padStart(2, '0')}:${String(agora.getSeconds()).padStart(2, '0')}`;

    if (resultadoInfo.anomalia) {
      // ==========================================
      // CASO A: ANOMALIA DETECTADA
      // ==========================================
      if (badge) {
        badge.className = 'badge-teste-status status-anomalia status-alerta';
        badge.textContent = '⚠️ ANOMALIA DETECTADA';
      }

      if (card) {
        card.classList.add('card-teste-anomalia', 'card-anomalia');
      }

      if (caixaResultado) {
        caixaResultado.className = 'caixa-resultado-teste resultado-anomalia';
        caixaResultado.innerHTML = `
          <div class="resultado-cabecalho">
            <span class="resultado-tag">⚠️ ANOMALIA IDENTIFICADA</span>
            <span class="resultado-hora">${horaFormatada}</span>
          </div>
          <div class="resultado-corpo">
            <p class="resultado-msg">${escapeHtml(resultadoInfo.mensagem)}</p>
            ${dadosTeste.comando_ou_acao ? `
              <div class="resultado-comando">
                <span class="comando-label">Ação / Sonda Executada:</span>
                <code>${escapeHtml(dadosTeste.comando_ou_acao)}</code>
              </div>
            ` : ''}
          </div>
        `;
        caixaResultado.style.display = 'block';
      }

      // Log no Terminal (ALERTA)
      adicionarLogTerminal(`[ALERTA CRÍTICO] ${resultadoInfo.mensagem}`, 'alerta');

    } else {
      // ==========================================
      // CASO B: TELEMETRIA NORMAL / SAUDÁVEL
      // ==========================================
      if (badge) {
        badge.className = 'badge-teste-status status-saudavel status-concluido';
        badge.textContent = '✓ NORMAL / SAUDÁVEL';
      }

      if (card) {
        card.classList.add('card-teste-saudavel', 'card-saudavel');
      }

      if (caixaResultado) {
        caixaResultado.className = 'caixa-resultado-teste resultado-ok';
        caixaResultado.innerHTML = `
          <div class="resultado-cabecalho">
            <span class="resultado-tag">✓ TELEMETRIA NOMINAL</span>
            <span class="resultado-hora">${horaFormatada}</span>
          </div>
          <div class="resultado-corpo">
            <p class="resultado-msg">${escapeHtml(resultadoInfo.mensagem)}</p>
            ${dadosTeste.comando_ou_acao ? `
              <div class="resultado-comando">
                <span class="comando-label">Ação / Sonda Executada:</span>
                <code>${escapeHtml(dadosTeste.comando_ou_acao)}</code>
              </div>
            ` : ''}
          </div>
        `;
        caixaResultado.style.display = 'block';
      }

      // Log no Terminal (SUCESSO)
      adicionarLogTerminal(`[TELEMETRIA NOMINAL] ${resultadoInfo.mensagem}`, 'sucesso');
    }

  }, 1300);
}

/**
 * Determina o resultado técnico final do teste com base nos dados da API ou na análise contextual
 * @param {Object} teste Objeto do teste
 * @param {string} defeitoId ID do defeito ativo
 * @returns {Object} { anomalia: boolean, mensagem: string }
 */
function obterResultadoTesteFinal(teste, defeitoId) {
  // Se a API retornou o campo anomalia explícito, seleciona a mensagem técnica adequada
  if (typeof teste.anomalia === 'boolean') {
    let msgTecnica = teste.resultado_tecnico;
    if (!msgTecnica) {
      if (teste.anomalia && teste.resultado_anomalia) {
        msgTecnica = teste.resultado_anomalia;
      } else if (!teste.anomalia && (teste.resultado_normal || teste.resultado_esperado)) {
        msgTecnica = teste.resultado_normal || `Telemetria Nominal: ${teste.resultado_esperado}`;
      }
    }

    if (msgTecnica) {
      return {
        anomalia: teste.anomalia,
        mensagem: msgTecnica
      };
    }
  }

  // Fallback Inteligente baseado no defeito ativo e títulos da tela
  return gerarResultadoTesteFallback(teste.nome || '', teste, defeitoId);
}

/**
 * Gera mensagem e status de resultado contextualizado para o teste executado (Modo Fallback Inteligente)
 * @param {string} nome Nome do teste
 * @param {Object} teste Objeto completo do teste
 * @param {string} defeitoId ID do defeito atual
 * @returns {Object} { anomalia: boolean, mensagem: string }
 */
function gerarResultadoTesteFallback(nome, teste, defeitoId) {
  const nomeLower = (nome || '').toLowerCase();
  const descLower = (teste.descricao || '').toLowerCase();
  const defId = String(defeitoId || '').trim();

  // Obtém títulos e descrições do defeito presentes no DOM para reforçar a detecção
  const elTituloDef = document.getElementById('tituloDefeito');
  const elTextoDef = document.getElementById('textoStatusSistema');
  const tituloDef = elTituloDef ? elTituloDef.innerText.toLowerCase() : '';
  const textoDef = elTextoDef ? elTextoDef.innerText.toLowerCase() : '';

  // 1. Defeito 1: "Computador não liga" (Causa: Fonte de Alimentação)
  if (defId === '1' || tituloDef.includes('não liga') || textoDef.includes('não liga') || textoDef.includes('nenhum led')) {
    if (nomeLower.includes('fonte') || nomeLower.includes('tensão') || nomeLower.includes('psu') || nomeLower.includes('alimentação')) {
      return {
        anomalia: true,
        mensagem: 'FALHA CRÍTICA DE ALIMENTAÇÃO: Linhas +12V e +5V com 0.00V e sinal Power_Good (PG) ausente (0ms). A fonte de alimentação (PSU) está inoperante ou com circuito de chaveamento rompido.'
      };
    }
    if (nomeLower.includes('post') || nomeLower.includes('barramentos') || (nomeLower.includes('geral') && nomeLower.includes('global'))) {
      return {
        anomalia: true,
        mensagem: 'FALHA DE ENERGIA GERAL: Sem sinal Power_Good no barramento principal de 24 pinos. A placa-mãe não recebe energização necessária para iniciar o ciclo POST.'
      };
    }
  }

  // 2. Defeito 2: "Tela Azul" (Causa: Reinstalar SO / Arquivos de Sistema Corrompidos)
  if (defId === '2' || tituloDef.includes('azul') || textoDef.includes('azul') || textoDef.includes('bsod')) {
    if (nomeLower.includes('arquivo') || nomeLower.includes('sfc') || nomeLower.includes('dism') || nomeLower.includes('boot') || (nomeLower.includes('integridade') && nomeLower.includes('sistema'))) {
      return {
        anomalia: true,
        mensagem: 'CORRUPÇÃO CRÍTICA DO SISTEMA: A ferramenta SFC/DISM identificou que arquivos essenciais do kernel (ntoskrnl.exe / winload.efi) estão corrompidos e irrecuperáveis, impedindo a inicialização do Windows.'
      };
    }
    if (nomeLower.includes('eventos') || nomeLower.includes('minidump') || nomeLower.includes('dump') || nomeLower.includes('log de eventos')) {
      return {
        anomalia: true,
        mensagem: 'DUMP DE MEMÓRIA CRÍTICO (BSOD): Registros do Event Viewer confirmam múltiplos eventos de BugCheck 0x0000007B (INACCESSIBLE_BOOT_DEVICE / SYSTEM_SERVICE_EXCEPTION) na inicialização.'
      };
    }
  }

  // 3. Defeito 3: "Superaquecimento" (Causa: Cooler / Pasta Térmica)
  if (defId === '3' || tituloDef.includes('aquecimento') || textoDef.includes('aquecimento') || textoDef.includes('temperatura')) {
    if (nomeLower.includes('cooler') || nomeLower.includes('térmico') || nomeLower.includes('temperatura') || nomeLower.includes('refrigeração') || nomeLower.includes('estresse')) {
      return {
        anomalia: true,
        mensagem: 'SUPERAQUECIMENTO CRÍTICO: Temperatura da CPU atingiu 99°C em 20 segundos de estresse térmico. Ventoinha travada (0 RPM) e pasta térmica ressecada, ativando o desligamento de proteção térmica (TJMax).'
      };
    }
    if (nomeLower.includes('estresse térmico') || nomeLower.includes('carga combinada') || nomeLower.includes('estresse integrado')) {
      return {
        anomalia: true,
        mensagem: 'DESLIGAMENTO TÉRMICO DE EMERGÊNCIA: A CPU ultrapassou a temperatura limite de segurança em ciclo rápido de estresse devido à falha total do conjunto de dissipação do cooler.'
      };
    }
  }

  // 4. Defeito 4: "Travamento no sistema operacional" (Causa: Procurar Vírus / Minerador)
  if (defId === '4' || tituloDef.includes('travamento') || textoDef.includes('duvidoso') || textoDef.includes('recursos') || textoDef.includes('travamento')) {
    if (nomeLower.includes('ameaça') || nomeLower.includes('vírus') || nomeLower.includes('malware') || nomeLower.includes('antivírus') || nomeLower.includes('spyware')) {
      return {
        anomalia: true,
        mensagem: 'MALWARE DE ALTO CONSUMO DETECTADO: Trojan.CoinMiner identificado ativo no processo "winlogon_fake.exe" (PID 4820), consumindo 100% dos ciclos de processamento e gerando travamentos generalizados.'
      };
    }
    if (nomeLower.includes('processos') || nomeLower.includes('rootkit') || nomeLower.includes('minerador') || nomeLower.includes('processos ocultos')) {
      return {
        anomalia: true,
        mensagem: 'PROCESSO MALICIOSO EM EXECUÇÃO: Thread não assinada consumindo 98% de CPU/RAM e gerando conexões TCP suspeitas para pools de mineração de criptomoedas.'
      };
    }
    if (nomeLower.includes('eventos') || nomeLower.includes('log de eventos')) {
      return {
        anomalia: true,
        mensagem: 'ALERTA DE SEGURANÇA NO LOG: Múltiplas tentativas de injeção de código em processos do sistema e alertas de esgotamento de memória por processo desconhecido.'
      };
    }
  }

  // 5. Defeito 5: "A resolução de vídeo baixa" (Causa: Atualização / Driver de Vídeo)
  if (defId === '5' || tituloDef.includes('resolução') || textoDef.includes('resolução') || textoDef.includes('800x600')) {
    if (nomeLower.includes('driver') || nomeLower.includes('update') || nomeLower.includes('atualização') || nomeLower.includes('dispositivo')) {
      return {
        anomalia: true,
        mensagem: 'DRIVER DE VÍDEO OU ÁUDIO CORROMPIDO / DESATUALIZADO: O controlador de vídeo/áudio encontra-se com falha. O sistema reverteu para o adaptador de vídeo básico da Microsoft.'
      };
    }
    if (nomeLower.includes('renderização') || nomeLower.includes('gpu') || nomeLower.includes('3d') || nomeLower.includes('vram')) {
      return {
        anomalia: true,
        mensagem: 'ACELERAÇÃO GRÁFICA INDISPONÍVEL: Falha na inicialização do DirectX/Vulkan. O subsistema de vídeo opera em modo de compatibilidade de baixa resolução por ausência do driver proprietário correto.'
      };
    }
    if (nomeLower.includes('eventos') || nomeLower.includes('log de eventos')) {
      return {
        anomalia: true,
        mensagem: 'EVENTO CRÍTICO DE VÍDEO: Registrado Event ID 411 (Display Driver Failed to Load) após o desligamento abrupto de energia.'
      };
    }
  }

  // Resultados Nominais Padrão quando não há anomalia no teste
  if (nomeLower.includes('memória') || nomeLower.includes('ram') || nomeLower.includes('memtest')) {
    return {
      anomalia: false,
      mensagem: `${nome}: 0 erros encontrados nos endereços de memória 0x0000-0xFFFF. Módulos de RAM operando com sincronismo e frequência nominal em Dual-Channel.`
    };
  }

  if (nomeLower.includes('s.m.a.r.t.') || nomeLower.includes('disco') || nomeLower.includes('armazenamento') || nomeLower.includes('ssd') || nomeLower.includes('hd')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Unidade de armazenamento saudável (100% de vida útil). 0 setores realocados (Bad Sectors) e taxas de transferência nominais.`
    };
  }

  if (nomeLower.includes('fonte') || nomeLower.includes('tensão') || nomeLower.includes('psu') || nomeLower.includes('alimentação')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Linhas de tensão +12V (12.08V), +5V (5.02V) e +3.3V (3.31V) perfeitamente estáveis. Sinal Power Good (PG) ativo em 310ms.`
    };
  }

  if (nomeLower.includes('cooler') || nomeLower.includes('refrigeração') || nomeLower.includes('térmico')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Dissipação térmica eficiente com cooler a 1.950 RPM. Temperatura do processador estabilizada em 42°C em repouso e 66°C sob carga.`
    };
  }

  if (nomeLower.includes('ping') || nomeLower.includes('icmp') || nomeLower.includes('latência')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Latência média de 11ms para o gateway e servidores DNS. 0% de perda de pacotes e jitter inferior a 2ms.`
    };
  }

  if (nomeLower.includes('adaptador') || nomeLower.includes('ethernet') || nomeLower.includes('wi-fi') || nomeLower.includes('linkspeed')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Interface de rede conectada e sincronizada a 1000 Mbps Full Duplex. Pilha TCP/IP respondendo perfeitamente.`
    };
  }

  if (nomeLower.includes('dns') || nomeLower.includes('resolução de nomes')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Servidores DNS respondendo em 14ms. Cache de resolução limpo e arquivo hosts íntegro sem redirecionamentos.`
    };
  }

  if (nomeLower.includes('rota') || nomeLower.includes('traceroute') || nomeLower.includes('jitter')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Rota traçada até o destino em 6 saltos sem desvios, sem perda de pacotes ou estrangulamento de banda.`
    };
  }

  if (nomeLower.includes('sfc') || nomeLower.includes('arquivos do sistema') || nomeLower.includes('dism')) {
    return {
      anomalia: false,
      mensagem: `${nome}: A Proteção de Recursos do Windows não encontrou nenhuma violação de integridade nos arquivos e bibliotecas do sistema operacional.`
    };
  }

  if (nomeLower.includes('malware') || nomeLower.includes('vírus') || nomeLower.includes('spyware') || nomeLower.includes('rootkit') || nomeLower.includes('ameaças')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Varredura profunda concluída. 0 ameaças ativas, malwares ou mineradores detectados na memória volátil ou no sistema de arquivos.`
    };
  }

  if (nomeLower.includes('bios') || nomeLower.includes('firmware') || nomeLower.includes('secure boot') || nomeLower.includes('uefi')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Firmware UEFI na versão mais recente. Secure Boot ativado, bateria CMOS com 3.2V e parâmetros de inicialização íntegros.`
    };
  }

  if (nomeLower.includes('portas') || nomeLower.includes('conexões') || nomeLower.includes('netstat')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Todas as portas abertas correspondem a serviços conhecidos e assinados. 0 conexões remotas não autorizadas ou suspeitas.`
    };
  }

  if (nomeLower.includes('placa-mãe') || nomeLower.includes('barramentos') || nomeLower.includes('post')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Barramentos PCIe, chipset e controladores I/O respondendo dentro das especificações. Ciclo POST executado com sucesso.`
    };
  }

  if (nomeLower.includes('contatos') || nomeLower.includes('poeira') || nomeLower.includes('limpeza')) {
    return {
      anomalia: false,
      mensagem: `${nome}: Contatos dourados dos slots de memória e barramentos limpos e sem oxidação. Dutos de ventilação 100% desobstruídos.`
    };
  }

  return {
    anomalia: false,
    mensagem: `${nome}: Varredura técnica concluída com sucesso. Todos os parâmetros operacionais encontram-se dentro dos limites nominais.`
  };
}

/**
 * Adiciona uma linha formatada no console de terminal do diagnóstico
 * @param {string} mensagem Texto do log
 * @param {string} tipo 'info', 'sucesso', 'alerta', 'destaque'
 */
function adicionarLogTerminal(mensagem, tipo = 'info') {
  const terminal = document.getElementById('diagTerminalLog');
  if (!terminal) return;

  const linha = document.createElement('div');
  linha.className = `log-linha log-${tipo}`;

  const agora = new Date();
  const tempo = `${String(agora.getHours()).padStart(2, '0')}:${String(agora.getMinutes()).padStart(2, '0')}:${String(agora.getSeconds()).padStart(2, '0')}`;

  const timestamp = document.createElement('span');
  timestamp.className = 'log-timestamp';
  timestamp.textContent = `[${tempo}] `;

  const texto = document.createElement('span');
  texto.textContent = mensagem;

  linha.appendChild(timestamp);
  linha.appendChild(texto);

  terminal.appendChild(linha);

  // Rola automaticamente para o fim dos logs
  terminal.scrollTop = terminal.scrollHeight;
}

/**
 * Retorna ícones representativos para cada subsistema
 */
function obterIconePorTipo(tipo) {
  const t = (tipo || '').toLowerCase();
  switch (t) {
    case 'hardware':
      return '⚙️';
    case 'software':
      return '💿';
    case 'rede':
      return '🌐';
    case 'seguranca':
      return '🛡️';
    case 'geral':
    default:
      return '⚡';
  }
}

/**
 * Escapa strings HTML para prevenir XSS
 */
function escapeHtml(texto) {
  if (!texto) return '';
  const div = document.createElement('div');
  div.textContent = String(texto);
  return div.innerHTML;
}

/**
 * Gera conjunto de testes técnicos padrão enriquecidos para cada uma das 5 categorias (Modo Resiliente)
 * @param {string} tipo Categoria (hardware, software, rede, seguranca, geral)
 * @param {string} defeitoId ID do defeito atual
 * @returns {Array} Lista de testes
 */
function gerarTestesFallback(tipo, defeitoId) {
  const t = (tipo || '').toLowerCase();
  let testes = [];

  switch (t) {
    case 'hardware':
      testes = [
        {
          id: 'hw_fonte',
          tipo: 'hardware',
          icone_emoji: '🔌',
          nome: 'Teste de Tensão da Fonte (PSU)',
          descricao: 'Mede as linhas de tensão (+12V, +5V, +3.3V) e o sinal Power_Good (PG) da fonte com multímetro digital em carga contínua.',
          comando_ou_acao: 'Medição de pinagem ATX 24 pinos com multímetro digital em escala DCV e carga resistiva',
          resultado_esperado: 'Tensões dentro da tolerância de ±5% (+12V: 11.4V-12.6V, +5V: 4.75V-5.25V, +3.3V: 3.14V-3.47V) e sinal PG ativo',
          tempo_estimado: '~1.5s'
        },
        {
          id: 'hw_memoria',
          tipo: 'hardware',
          icone_emoji: '🧠',
          nome: 'Teste de Integridade de Memória (MemTest)',
          descricao: 'Executa testes de estresse e padrões de bits em cada módulo de RAM para detectar blocos e endereços defeituosos.',
          comando_ou_acao: 'MemTest86 / Windows Memory Diagnostic loop de padrões de bits (Moving Inversions)',
          resultado_esperado: '0 erros encontrados em todos os passes de leitura e escrita nos endereços 0x0000-0xFFFF',
          tempo_estimado: '~2.0s'
        },
        {
          id: 'hw_cooler_termico',
          tipo: 'hardware',
          icone_emoji: '❄️',
          nome: 'Monitoramento Térmico e Rotação do Cooler',
          descricao: 'Aplica carga máxima na CPU para monitorar temperatura em tempo real, rotação do cooler (RPM) e acionamento de Thermal Throttling.',
          comando_ou_acao: 'Leitura dos sensores térmicos via HWMonitor com monitoramento de tacômetro PWM',
          resultado_esperado: 'Temperatura máxima estabilizada abaixo de 75°C e fan operando na curva PWM nominal',
          tempo_estimado: '~1.8s'
        },
        {
          id: 'hw_disco',
          tipo: 'hardware',
          icone_emoji: '💾',
          nome: 'Diagnóstico S.M.A.R.T. de Armazenamento',
          descricao: 'Verifica a saúde das células NAND Flash do SSD / pratos do HD, contagem de setores realocados (Bad Sectors) e taxa de leitura.',
          comando_ou_acao: 'Leitura de telemetria CrystalDiskInfo / smartctl -a /dev/sda com autoteste S.M.A.R.T.',
          resultado_esperado: 'Status "Saudável" (100% de vida útil e 0 setores pendentes de realocação)',
          tempo_estimado: '~1.2s'
        },
        {
          id: 'hw_gpu',
          tipo: 'hardware',
          icone_emoji: '🎮',
          nome: 'Teste de Renderização 3D e VRAM da GPU',
          descricao: 'Renderiza malha 3D de alta intensidade para identificar artefatos visuais, integridade dos chips VRAM e VRMs da placa de vídeo.',
          comando_ou_acao: 'Execução de loop de renderização FurMark / 3DMark em resolução nativa',
          resultado_esperado: 'Renderização contínua sem artefatos visuais, distorção de cores ou crash do driver gráfico',
          tempo_estimado: '~2.0s'
        },
        {
          id: 'hw_placamae',
          tipo: 'hardware',
          icone_emoji: '🖥️',
          nome: 'Diagnóstico de Barramentos da Placa-Mãe',
          descricao: 'Inspeciona tensões de VRM da placa-mãe, integridade dos slots PCIe, barramento do chipset e portas de expansão.',
          comando_ou_acao: 'Inspeção de telemetria dos sensores I/O e teste de loopback nos barramentos da placa-mãe',
          resultado_esperado: 'Todos os barramentos PCIe/Chipset respondendo nos endereços nominais sem capacitores estufados',
          tempo_estimado: '~1.5s'
        },
        {
          id: 'hw_limpeza',
          tipo: 'hardware',
          icone_emoji: '🧹',
          nome: 'Inspeção de Contatos e Poeira (Limpeza)',
          descricao: 'Avalia condutividade dos slots de memória/PCIe, obstrução de dutos de ar e presença de fuligem ou oxidação nos contatos.',
          comando_ou_acao: 'Inspeção visual e teste de continuidade elétrica nos pinos de contato com produto específico',
          resultado_esperado: 'Slots limpos, sem poeira acumulada, sem oxidação ou resíduos isolantes',
          tempo_estimado: '~1.0s'
        }
      ];
      break;

    case 'software':
      testes = [
        {
          id: 'sw_sfc',
          tipo: 'software',
          icone_emoji: '🛠️',
          nome: 'Verificação de Arquivos do Sistema (SFC / DISM)',
          descricao: 'Analisa e repara arquivos de sistema corrompidos ou ausentes no repositório do Sistema Operacional.',
          comando_ou_acao: 'sfc /scannow && DISM /Online /Cleanup-Image /RestoreHealth',
          resultado_esperado: 'A Proteção de Recursos do Windows não encontrou nenhuma violação de integridade nos arquivos de sistema',
          tempo_estimado: '~1.5s'
        },
        {
          id: 'sw_ameacas',
          tipo: 'software',
          icone_emoji: '🦠',
          nome: 'Varredura Completa Antivírus e Rootkits',
          descricao: 'Escaneia processos ativos na memória RAM, serviços ocultos e pastas de sistema em busca de malwares e trojans.',
          comando_ou_acao: 'Varredura heurística profunda via Windows Defender / Malwarebytes no sistema e memória',
          resultado_esperado: '0 ameaças ativas detectadas na memória e no sistema de arquivos',
          tempo_estimado: '~2.0s'
        },
        {
          id: 'sw_drivers',
          tipo: 'software',
          icone_emoji: '🔄',
          nome: 'Auditoria de Drivers e Windows Update',
          descricao: 'Detecta drivers corrompidos, desatualizados ou com código de erro (Código 43) no Gerenciador de Dispositivos.',
          comando_ou_acao: 'driverquery /v /fo list && usoclient StartInteractiveScan',
          resultado_esperado: 'Todos os dispositivos com drivers certificados, válidos e sem códigos de erro',
          tempo_estimado: '~1.2s'
        },
        {
          id: 'sw_bios',
          tipo: 'software',
          icone_emoji: '⚙️',
          nome: 'Auditoria de Configurações da BIOS/UEFI',
          descricao: 'Analisa ordem de inicialização (Boot Order), integridade da bateria CMOS e configurações de firmware UEFI.',
          comando_ou_acao: 'Leitura da tabela SMBIOS via WMI e conferência de NVRAM',
          resultado_esperado: 'Firmware UEFI íntegro, ordem de boot correta e data/hora sincronizadas',
          tempo_estimado: '~1.0s'
        },
        {
          id: 'sw_boot',
          tipo: 'software',
          icone_emoji: '🚀',
          nome: 'Análise de Inicialização e Serviços',
          descricao: 'Inspeciona programas de inicialização automática, serviços em execução em segundo plano e chaves Run do registro.',
          comando_ou_acao: 'Get-CimInstance Win32_StartupCommand | Format-Table -AutoSize',
          resultado_esperado: 'Inicialização limpa e serviços operando normalmente sem scripts anômalos',
          tempo_estimado: '~1.0s'
        }
      ];
      break;

    case 'rede':
      testes = [
        {
          id: 'net_ping',
          tipo: 'rede',
          icone_emoji: '📶',
          nome: 'Teste de Latência e Perda de Pacotes (Ping / ICMP)',
          descricao: 'Envia pacotes de controle ao gateway padrão e servidores de referência para medir jitter, perda e estabilidade.',
          comando_ou_acao: 'ping -n 4 8.8.8.8 && tracert -d 1.1.1.1',
          resultado_esperado: '0% de perda de pacotes e latência estável inferior a 20ms',
          tempo_estimado: '~1.2s'
        },
        {
          id: 'net_adapter',
          tipo: 'rede',
          icone_emoji: '🔌',
          nome: 'Diagnóstico do Adaptador de Rede (Ethernet / Wi-Fi)',
          descricao: 'Testa o estado físico da interface de rede, velocidade de negociação (LinkSpeed 1000 Mbps) e pilha TCP/IP.',
          comando_ou_acao: 'Get-NetAdapter | Format-Table Name, Status, LinkSpeed',
          resultado_esperado: 'Interface conectada em 1000 Mbps Full Duplex sem perda de sincronismo',
          tempo_estimado: '~1.0s'
        },
        {
          id: 'net_dns',
          tipo: 'rede',
          icone_emoji: '🌐',
          nome: 'Resolução de Nomes e Integridade de Cache DNS',
          descricao: 'Avalia a resposta dos servidores DNS primário e secundário e checa a integridade do arquivo hosts.',
          comando_ou_acao: 'ipconfig /flushdns && Resolve-DnsName google.com',
          resultado_esperado: 'Resolução DNS instantânea (< 20ms) sem redirecionamentos suspeitos',
          tempo_estimado: '~1.0s'
        },
        {
          id: 'net_routes',
          tipo: 'rede',
          icone_emoji: '🗺️',
          nome: 'Rastreamento de Rotas e Jitter (Traceroute)',
          descricao: 'Mapeia os saltos de rede entre o computador e os backbones de internet para detectar gargalos.',
          comando_ou_acao: 'tracert -d -h 10 1.1.1.1',
          resultado_esperado: 'Rota completada em menos de 8 saltos sem estrangulamento de pacotes',
          tempo_estimado: '~1.5s'
        }
      ];
      break;

    case 'seguranca':
      testes = [
        {
          id: 'sec_malware',
          tipo: 'seguranca',
          icone_emoji: '🛡️',
          nome: 'Varredura Rápida de Malware e Spyware',
          descricao: 'Procura por assinaturas maliciosas ativas em execução na memória volátil e diretórios de execução temporária.',
          comando_ou_acao: 'MpCmdRun.exe -Scan -ScanType 1',
          resultado_esperado: '0 ameaças ativas encontradas na memória volátil e no registro',
          tempo_estimado: '~1.5s'
        },
        {
          id: 'sec_processes',
          tipo: 'seguranca',
          icone_emoji: '🔍',
          nome: 'Auditoria de Processos Ocultos e Cripto-Mineradores',
          descricao: 'Mapeia processos com consumo anômalo de processador/placa de vídeo e threads ocultas de injeção de código.',
          comando_ou_acao: 'Get-Process | Where-Object {$_.CPU -gt 50} | Select-Object Id, ProcessName, CPU',
          resultado_esperado: 'Nenhum processo desconhecido consumindo recursos de forma anômala',
          tempo_estimado: '~1.4s'
        },
        {
          id: 'sec_integrity',
          tipo: 'seguranca',
          icone_emoji: '🔐',
          nome: 'Verificação de Integridade da BIOS / Firmware & Secure Boot',
          descricao: 'Confere o hash de autenticidade da BIOS e chaves do Secure Boot contra modificações e rootkits de firmware.',
          comando_ou_acao: 'Confirm-SecureBootUEFI && Get-CimInstance Win32_BIOS',
          resultado_esperado: 'Secure Boot habilitado e firmware autenticado com chave PK válida',
          tempo_estimado: '~1.2s'
        },
        {
          id: 'sec_ports',
          tipo: 'seguranca',
          icone_emoji: '🚪',
          nome: 'Inspeção de Conexões Remotas Ativas e Portas Abertas',
          descricao: 'Examina conexões de rede ativas (ESTABLISHED) e escuta de portas para detectar backdoors ou tráfego C2.',
          comando_ou_acao: 'netstat -ano -p tcp | findstr "ESTABLISHED LISTENING"',
          resultado_esperado: '0 conexões remotas não autorizadas ou portas em escuta suspeita',
          tempo_estimado: '~1.2s'
        }
      ];
      break;

    case 'geral':
    default:
      testes = [
        {
          id: 'gen_full',
          tipo: 'geral',
          icone_emoji: '⚡',
          nome: 'Varredura Global de Barramentos & POST de Hardware',
          descricao: 'Executa autoteste de inicialização inspecionando simultaneamente fornecimento de energia, barramentos PCIe e sinal PG.',
          comando_ou_acao: 'Execução de rotina de autoteste e diagnóstico de barramentos PCIe/SATA/USB e conector ATX',
          resultado_esperado: 'Todos os barramentos respondendo nos tempos limite nominais com sinal PG ativo',
          tempo_estimado: '~2.0s'
        },
        {
          id: 'gen_stress',
          tipo: 'geral',
          icone_emoji: '🔥',
          nome: 'Teste de Estresse Integrado (CPU, GPU e VRM)',
          descricao: 'Submete o processador, conjunto gráfico e circuitos de energia a carga combinada para checar estabilidade térmica e elétrica.',
          comando_ou_acao: 'Carga combinada CPU+FPU+VRAM por ciclo rápido de medição de telemetria',
          resultado_esperado: 'Sistema totalmente estável sem reinicializações, travamentos ou thermal throttling',
          tempo_estimado: '~2.2s'
        },
        {
          id: 'gen_eventos',
          tipo: 'geral',
          icone_emoji: '📋',
          nome: 'Leitura do Log de Eventos Críticos e Minidumps',
          descricao: 'Analisa despejos de memória (minidumps), códigos de tela azul (BSOD), eventos de driver e desligamentos inesperados.',
          comando_ou_acao: 'Get-WinEvent -FilterHashtable @{LogName=\'System\'; Level=1,2} -MaxEvents 10',
          resultado_esperado: '0 eventos críticos de falha de hardware ou kernel registrados nas últimas 24 horas',
          tempo_estimado: '~1.2s'
        },
        {
          id: 'gen_power_sensors',
          tipo: 'geral',
          icone_emoji: '💡',
          nome: 'Diagnóstico de Eficiência Energética e Estabilidade de Tensão',
          descricao: 'Mede a estabilidade da tensão entregue às trilhas de alimentação da placa-mãe sob variação de consumo.',
          comando_ou_acao: 'Leitura dos sensores de telemetria SIO e VRM de alimentação',
          resultado_esperado: 'Flutuação de tensão inferior a 2% sob carga sem queda de fase',
          tempo_estimado: '~1.4s'
        }
      ];
      break;
  }

  // Enriquece os testes de fallback com o cálculo de anomalia prévio
  return testes.map(t => {
    const res = gerarResultadoTesteFallback(t.nome, t, defeitoId);
    return {
      ...t,
      anomalia: res.anomalia,
      resultado_tecnico: res.mensagem,
      status_sugerido: res.anomalia ? 'anomalia' : 'saudavel'
    };
  });
}

// Fechamento ao clicar fora do modal (overlay)
document.addEventListener('click', function (evento) {
  const modal = document.getElementById('modalDiagnostico');
  if (modal && evento.target === modal) {
    fecharModalDiagnostico();
  }
});

// Exportação explícita de funções para escopo global (Window)
window.abrirModalDiagnostico = abrirModalDiagnostico;
window.fecharModalDiagnostico = fecharModalDiagnostico;
window.selecionarCategoriaDiagnostico = selecionarCategoriaDiagnostico;
window.voltarParaCategorias = voltarParaCategorias;
window.carregarTestes = carregarTestes;
window.executarTesteIndividual = executarTesteIndividual;
window.adicionarLogTerminal = adicionarLogTerminal;

