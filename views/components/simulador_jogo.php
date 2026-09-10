<?php
/**
 * Componente Reutilizável de Jogo / Simulador de Manutenção - Inteldle
 * Renderiza a interface completa: Topo (imagem e descrição), Bandejas de Hardware/Software,
 * Botão Power e Modais de Aviso e Status.
 *
 * Parâmetros esperados (opcionais):
 * - $defeito: array com chaves 'id', 'titulo', 'descricao', 'imagem', 'tipo'
 * - $db: instância PDO de conexão com o banco SQLite
 */
if (!isset($db) || !($db instanceof PDO)) {
    require_once __DIR__ . '/../../config/database.php';
    $db = (new Database())->conectar();
}

$defeito = $defeito ?? [
    'id' => '',
    'titulo' => 'Diagnóstico de Sistema',
    'descricao' => 'Selecione a ação adequada para resolver o problema apresentado.',
    'imagem' => 'monitor.png',
    'tipo' => 'hardware'
];

// Carregamento dinâmico dos componentes do banco SQLite (3FN)
$componentesHardware = $db->query("SELECT id, nome, tipo, icone, descricao FROM componentes WHERE tipo = 'hardware' ORDER BY id ASC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$componentesSoftware = $db->query("SELECT id, nome, tipo, icone, descricao FROM componentes WHERE tipo = 'software' ORDER BY id ASC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Modal de Aviso de Segurança -->
<div id="modalAviso" class="aviso-overlay">
  <div class="aviso-content">
    <h2 id="avisoTitulo">ALERTA DE SEGURANÇA</h2>
    <p id="avisoTexto"></p>
    <button type="button" class="btn-ok" onclick="fecharAviso()">ENTENDIDO</button>
  </div>
</div>

<!-- Modal de Status (Feedback Correto / Incorreto) -->
<div id="modalStatus" class="status-overlay">
  <div id="statusContent" class="status-content status-ok">
    <h2 id="statusTitulo" class="status-titulo">CORRETO</h2>
    <p id="statusMensagem" class="status-mensagem"></p>
    <div id="statusAcoes" class="status-acoes">
      <button type="button" id="btnStatusOk" class="btn-ok" onclick="fecharStatus()">ENTENDIDO</button>
      <button type="button" id="btnProximoDefeito" class="btn-proximo-defeito btn-ok" onclick="proximoDefeito()" style="display:none;">PRÓXIMO DEFEITO →</button>
    </div>
  </div>
</div>

<!-- Modal de Game Over (Modo Infinito) -->
<div id="modalGameOver" class="gameover-overlay" style="display:none;">
  <div class="gameover-content">
    <div class="gameover-icone">💀</div>
    <h2 id="gameOverTitulo" class="gameover-titulo">GAME OVER</h2>
    <p id="gameOverMensagem" class="gameover-mensagem">Suas vidas acabaram!</p>
    <div class="gameover-stats">
      <div class="gameover-stat-item">
        <span class="gameover-stat-rotulo">Sequência Final:</span>
        <span class="gameover-stat-valor" id="gameOverStreak">0</span>
        <span>🔥</span>
      </div>
      <div class="gameover-stat-item">
        <span class="gameover-stat-rotulo">Recorde:</span>
        <span class="gameover-stat-valor" id="gameOverRecorde">0</span>
        <span>🏆</span>
      </div>
    </div>
    <div class="gameover-acoes">
      <button type="button" id="btnGameOverReiniciar" class="btn-ok btn-reiniciar" onclick="reiniciarJogoInfinito()">JOGAR NOVAMENTE 🔄</button>
      <a href="menu.php" class="btn-ok btn-voltar-menu" style="text-decoration: none; display: inline-block;">VOLTAR AO MENU 🏠</a>
    </div>
  </div>
</div>

<!-- Modal da Central de Diagnóstico e Testes do Sistema -->
<div id="modalDiagnostico" class="modal-diagnostico-overlay" style="display:none;" role="dialog" aria-labelledby="diagModalTitulo" aria-modal="true">
  <div class="modal-diagnostico-container">
    
    <!-- Cabeçalho Futurista Cyberpunk -->
    <div class="modal-diagnostico-header">
      <div class="diag-header-info">
        <span class="diag-header-icone">🔬</span>
        <div>
          <h2 id="diagModalTitulo" class="diag-header-titulo">CENTRAL DE DIAGNÓSTICO</h2>
          <span class="diag-header-subtitulo">SISTEMA INTELDLE v2.4 // VARREDURA E ANÁLISE TÉCNICA</span>
        </div>
      </div>
      <button type="button" class="btn-fechar-modal-diag" onclick="fecharModalDiagnostico()" aria-label="Fechar Central de Diagnóstico">✕</button>
    </div>

    <!-- Corpo do Modal -->
    <div class="modal-diagnostico-body">
      
      <!-- ETAPA 1: Seleção de Categorias -->
      <div id="diagnosticoEtapa1" class="diagnostico-etapa diagnostico-etapa-ativa">
        <div class="diag-etapa-instrucao">
          <span class="diag-instrucao-tag">ETAPA 01</span>
          <p>Selecione um subsistema para inspecionar parâmetros, sensores e executar testes de integridade:</p>
        </div>

        <div class="grid-categorias-diag">
          <!-- Categoria: Hardware -->
          <button type="button" class="card-categoria-diag card-cat-hardware" onclick="selecionarCategoriaDiagnostico('hardware', 'Hardware e Componentes Físicos')">
            <div class="cat-diag-icone">⚙️</div>
            <div class="cat-diag-conteudo">
              <h3 class="cat-diag-titulo">HARDWARE</h3>
              <p class="cat-diag-desc">Análise de CPU, RAM, GPU, Armazenamento, Fonte e Placa-Mãe.</p>
            </div>
            <span class="cat-diag-seta">→</span>
          </button>

          <!-- Categoria: Software -->
          <button type="button" class="card-categoria-diag card-cat-software" onclick="selecionarCategoriaDiagnostico('software', 'Software e Sistema Operacional')">
            <div class="cat-diag-icone">💿</div>
            <div class="cat-diag-conteudo">
              <h3 class="cat-diag-titulo">SOFTWARE & S.O.</h3>
              <p class="cat-diag-desc">Varredura de inicialização, arquivos de sistema, drivers e BIOS.</p>
            </div>
            <span class="cat-diag-seta">→</span>
          </button>

          <!-- Categoria: Rede
          <button type="button" class="card-categoria-diag card-cat-rede" onclick="selecionarCategoriaDiagnostico('rede', 'Rede e Conectividade')">
            <div class="cat-diag-icone">🌐</div>
            <div class="cat-diag-conteudo">
              <h3 class="cat-diag-titulo">REDE & CONEXÃO</h3>
              <p class="cat-diag-desc">Testes de ping, adaptador Ethernet/Wi-Fi, DNS e rotas.</p>
            </div>
            <span class="cat-diag-seta">→</span>
          </button> -->

          <!-- Categoria: Segurança -->
          <button type="button" class="card-categoria-diag card-cat-seguranca" onclick="selecionarCategoriaDiagnostico('seguranca', 'Segurança e Ameaças')">
            <div class="cat-diag-icone">🛡️</div>
            <div class="cat-diag-conteudo">
              <h3 class="cat-diag-titulo">SEGURANÇA & MALWARE</h3>
              <p class="cat-diag-desc">Detecção de vírus, trojans, mineradores e processos suspeitos.</p>
            </div>
            <span class="cat-diag-seta">→</span>
          </button>

          <!-- Categoria: Geral / Estresse -->
          <!--<button type="button" class="card-categoria-diag card-cat-geral card-categoria-geral" onclick="selecionarCategoriaDiagnostico('geral', 'Varredura Geral do Sistema')">
            <div class="cat-diag-icone">⚡</div>
            <div class="cat-diag-conteudo">
              <h3 class="cat-diag-titulo">VARREDURA GERAL</h3>
              <p class="cat-diag-desc">Diagnóstico abrangente de energia, barramentos e sensores térmicos.</p>
            </div>
            <span class="cat-diag-seta">→</span>
          </button> -->
        </div>
      </div>

      <!-- ETAPA 2: Lista Dinâmica de Testes -->
      <div id="diagnosticoEtapa2" class="diagnostico-etapa" style="display: none;">
        <div class="diag-etapa2-barra-nav">
          <button type="button" class="btn-voltar-diag" onclick="voltarParaCategorias()">
            ← Voltar às Categorias
          </button>
          <div class="diag-categoria-ativa-badge">
            <span class="diag-ponto-status"></span>
            <span id="diagCategoriaTitulo">Hardware e Componentes Físicos</span>
          </div>
        </div>

        <!-- Spinner de Carregamento -->
        <div id="diagLoadingSpinner" class="diag-loading-container" style="display: none;">
          <div class="spinner-cyberpunk"></div>
          <p class="diag-loading-texto">CONSULTANDO MATRIZ DE TESTES...</p>
        </div>

        <!-- Mensagem de Vazio / Erro -->
        <div id="diagMensagemVazia" class="diag-mensagem-vazia" style="display: none;">
          <span class="diag-vazio-icone">⚠️</span>
          <p id="diagTextoVazio">Nenhum teste disponível para este subsistema no momento.</p>
        </div>

        <!-- Lista de Cards de Testes -->
        <div id="diagListaTestes" class="lista-testes-diag">
          <!-- Inserido dinamicamente via diagnostico.js -->
        </div>

        <!-- Terminal de Logs / Saída de Diagnóstico -->
        <div class="diag-terminal-box">
          <div class="diag-terminal-header">
            <div class="diag-terminal-dots">
              <span class="diag-terminal-dot red"></span>
              <span class="diag-terminal-dot yellow"></span>
              <span class="diag-terminal-dot green"></span>
            </div>
            <span class="diag-terminal-title">CONSOLE DE LOGS EM TEMPO REAL // DIAGNOSTIC_OUTPUT.LOG</span>
          </div>
          <div id="diagTerminalLog" class="diag-terminal-corpo">
            <div class="log-linha log-info">
              <span class="log-timestamp">[00:00:00]</span> [SISTEMA] Central de diagnóstico inicializada e pronta.
            </div>
            <div class="log-linha log-destaque">
              <span class="log-timestamp">[00:00:00]</span> [DICA] Execute os testes para obter telemetria e pistas do defeito atual.
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Rodapé do Modal -->
    <div class="modal-diagnostico-footer">
      <span class="diag-footer-info">Inteldle Hardware Diagnostic Suite • Pressione ESC para fechar</span>
      <button type="button" class="btn-fechar-diag-footer" onclick="fecharModalDiagnostico()">FECHAR</button>
    </div>

  </div>
</div>

<!-- Topo: Imagem do Computador e Caixa de Descrição -->
<div class="topo-container">
  <div class="area-computador area-computador-interativa" onclick="abrirModalDiagnostico()" role="button" tabindex="0" title="Clique para abrir a Central de Diagnóstico e Testes" onkeydown="if(event.key==='Enter'||event.key===' ')abrirModalDiagnostico()">
    <div class="badge-monitor-diagnostico">
      <span class="badge-monitor-icone">🔬</span> CENTRAL DE DIAGNÓSTICO <span class="badge-monitor-dica">[CLIQUE]</span>
    </div>
    <img id="imgComputador" 
         src="../assets/imagens/<?= !empty($defeito['imagem']) ? htmlspecialchars($defeito['imagem']) : 'monitor.png' ?>" 
         alt="[Imagem do Computador]" 
         onerror="this.src='../assets/imagens/monitor.png'">
  </div>

  <div class="caixa-descricao">
    <h3 id="tituloDefeito" style="margin-top: 0;">
      <?= htmlspecialchars($defeito['titulo'] ?? 'Defeito do Sistema') ?>
    </h3>
    <p id="textoStatusSistema">
      <?= htmlspecialchars($defeito['descricao'] ?? '') ?>
    </p>
  </div>
</div>

<!-- Área de Controles: Bandejas Interativas e Botão Power -->
<div class="controles-area">
  <div class="secao-botoes-bandejas">

    <!-- Bandeja de Hardware -->
    <div class="grupo-interativo">
      <button type="button" class="btn-azul" onclick="toggleBandeja('bandejaHW')">HARDWARE</button>
      <div id="bandejaHW" class="linha-pecas">
        <?php foreach ($componentesHardware as $comp): ?>
          <button type="button" 
                  class="btn-vermelho" 
                  data-id="<?= (int)$comp['id'] ?>" 
                  data-nome="<?= htmlspecialchars($comp['nome']) ?>" 
                  title="<?= htmlspecialchars($comp['nome'] . ($comp['descricao'] ? ' - ' . $comp['descricao'] : '')) ?>"
                  onclick="acaoHardware(<?= (int)$comp['id'] ?>, '<?= htmlspecialchars(addslashes($comp['nome']), ENT_QUOTES, 'UTF-8') ?>', this)">
            <img src="../assets/imagens/<?= htmlspecialchars(!empty($comp['icone']) ? $comp['icone'] : 'cpu.png') ?>" 
                 alt="<?= htmlspecialchars($comp['nome']) ?>" 
                 onerror="this.src='../assets/imagens/cpu.png'">
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Bandeja de Software -->
    <div class="grupo-interativo">
      <button type="button" class="btn-azul" onclick="toggleBandeja('bandejaSW')">SOFTWARE</button>
      <div id="bandejaSW" class="linha-pecas">
        <?php foreach ($componentesSoftware as $comp): ?>
          <button type="button" 
                  class="btn-vermelho" 
                  data-id="<?= (int)$comp['id'] ?>" 
                  data-nome="<?= htmlspecialchars($comp['nome']) ?>" 
                  title="<?= htmlspecialchars($comp['nome'] . ($comp['descricao'] ? ' - ' . $comp['descricao'] : '')) ?>"
                  onclick="acaoSoftware(<?= (int)$comp['id'] ?>, '<?= htmlspecialchars(addslashes($comp['nome']), ENT_QUOTES, 'UTF-8') ?>', this)">
            <img src="../assets/imagens/<?= htmlspecialchars(!empty($comp['icone']) ? $comp['icone'] : 'cpu.png') ?>" 
                 alt="<?= htmlspecialchars($comp['nome']) ?>" 
                 onerror="this.src='../assets/imagens/cpu.png'">
          </button>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Botão Power com Estados On/Off -->
  <div class="area-power">
    <button type="button" id="btnPower" class="aceso" onclick="alternarEnergia()" aria-label="Alternar energia">
      <img id="imgOn" src="../assets/imagens/BtnOn.png" alt="Power ligado">
      <img id="imgOff" src="../assets/imagens/BtnOff.png" alt="Power desligado">
    </button>
  </div>
</div>
