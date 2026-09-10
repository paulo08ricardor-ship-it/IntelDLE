<?php
require_once __DIR__ . '/auth_check.php';

$db = (new Database())->conectar();

// Carrega a lista de componentes cadastrados para o select e filtros
$todosComponentes = $db->query("SELECT id, nome, tipo, icone FROM componentes ORDER BY tipo ASC, nome ASC")->fetchAll();

$filtroTipo = trim($_GET['tipo'] ?? '');
$filtroComponente = filter_var($_GET['componente_id'] ?? null, FILTER_VALIDATE_INT);

// Montagem da query com filtros opcionais
$where = [];
$params = [];

if (!empty($filtroTipo) && in_array($filtroTipo, ['hardware', 'software', 'rede', 'seguranca', 'geral'], true)) {
    $where[] = "ct.tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if ($filtroComponente) {
    $where[] = "ct.componente_id = :comp_id";
    $params[':comp_id'] = $filtroComponente;
}

$sqlWhere = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $db->prepare("
    SELECT ct.*, c.nome AS componente_nome, c.icone AS componente_icone
    FROM componentes_testes ct
    LEFT JOIN componentes c ON c.id = ct.componente_id
    $sqlWhere
    ORDER BY ct.tipo ASC, ct.id ASC
");
$stmt->execute($params);
$testes = $stmt->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(obterCsrfToken()) ?>">
  <title>Diagnósticos & Testes - Inteldle Admin</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <script src="../assets/js/tema.js"></script>
</head>

<body class="admin-body <?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>">

  <!-- Header Administrativo -->
  <header class="admin-navbar">
    <div class="admin-logo-area">
      <div class="admin-logo-texto">Inteldle</div>
      <span class="admin-badge-topo">Área Administrativa</span>
    </div>

    <div class="admin-user-nav">
      <button class="btn-toggle-tema" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
      <span class="admin-nome-user">Olá, <?= htmlspecialchars($_SESSION['nome']) ?></span>
      <a href="../views/menu.php" class="btn-admin-acao">← Voltar ao Jogo</a>
      <a href="../api/logout.php" class="btn-admin-acao btn-admin-perigo">Sair</a>
    </div>
  </header>

  <!-- Subnav -->
  <nav class="admin-subnav">
    <a href="index.php" class="admin-nav-tab">Visão Geral</a>
    <a href="defeitos.php" class="admin-nav-tab">Defeitos</a>
    <a href="componentes.php" class="admin-nav-tab">Componentes</a>
    <a href="solucoes.php" class="admin-nav-tab">Soluções</a>
    <a href="diagnosticos.php" class="admin-nav-tab active">Diagnósticos</a>
    <a href="usuarios.php" class="admin-nav-tab">Usuários & Permissões</a>
  </nav>

  <!-- Container -->
  <main class="admin-container">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao">Gestão de Diagnósticos & Sondas</h1>
        <div class="admin-subtitulo-secao">
          Configure a suíte de testes técnicos, comandos de telemetria, medições elétricas e detecção de anomalias.
        </div>
      </div>
      
      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- Filtro por Categoria -->
        <form method="GET" action="diagnosticos.php" style="margin: 0; display: flex; gap: 8px;">
          <select name="tipo" class="form-select" onchange="this.form.submit()" style="padding: 8px 12px; font-size: 13px;">
            <option value="">-- Todas as Categorias --</option>
            <option value="hardware" <?= $filtroTipo === 'hardware' ? 'selected' : '' ?>>Hardware</option>
            <option value="software" <?= $filtroTipo === 'software' ? 'selected' : '' ?>>Software</option>
            <option value="rede" <?= $filtroTipo === 'rede' ? 'selected' : '' ?>>Rede & Conectividade</option>
            <option value="seguranca" <?= $filtroTipo === 'seguranca' ? 'selected' : '' ?>>Segurança & Integridade</option>
            <option value="geral" <?= $filtroTipo === 'geral' ? 'selected' : '' ?>>Geral & Firmware</option>
          </select>

          <select name="componente_id" class="form-select" onchange="this.form.submit()" style="padding: 8px 12px; font-size: 13px;">
            <option value="">-- Todos os Componentes --</option>
            <?php foreach ($todosComponentes as $comp): ?>
              <option value="<?= $comp['id'] ?>" <?= $filtroComponente == $comp['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($comp['nome']) ?> (<?= ucfirst($comp['tipo']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <button class="btn-admin-acao btn-admin-destaque" onclick="abrirModalCriar()">+ Novo Teste</button>
      </div>
    </div>

    <!-- Tabela de Testes de Diagnóstico -->
    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th style="width: 100px;">Tipo</th>
            <th style="width: 60px; text-align: center;">Ícone</th>
            <th>Nome do Teste & Descrição</th>
            <th style="width: 150px;">Componente</th>
            <th>Comando / Ação</th>
            <th style="width: 90px;">Tempo</th>
            <th style="width: 130px; text-align: center;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($testes)): ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 35px; opacity: 0.7;">
                Nenhum teste de diagnóstico encontrado para os filtros selecionados.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($testes as $t): ?>
              <?php
                $badgeClass = match ($t['tipo']) {
                    'hardware' => 'badge-hardware',
                    'software' => 'badge-software',
                    'rede' => 'badge-rede',
                    'seguranca' => 'badge-seguranca',
                    'geral' => 'badge-geral',
                    default => 'badge-hardware'
                };
              ?>
              <tr>
                <td><strong>#<?= $t['id'] ?></strong></td>
                <td>
                  <span class="badge-status <?= $badgeClass ?>">
                    <?= ucfirst($t['tipo']) ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <img src="../assets/imagens/<?= htmlspecialchars($t['icone'] ?: 'cpu.png') ?>" 
                       alt="Ícone" 
                       class="thumb-tabela"
                       onerror="this.src='../assets/imagens/cpu.png'">
                </td>
                <td>
                  <strong style="color: var(--cor-texto-principal); font-size: 14px;"><?= htmlspecialchars($t['nome']) ?></strong>
                  <div style="font-size: 12px; color: var(--cor-texto-destaque); opacity: 0.85; margin-top: 4px; line-height: 1.4;">
                    <?= htmlspecialchars($t['descricao']) ?>
                  </div>
                </td>
                <td>
                  <?php if (!empty($t['componente_nome'])): ?>
                    <span style="font-weight: bold; color: var(--cor-texto-principal);">
                      🧩 <?= htmlspecialchars($t['componente_nome']) ?>
                    </span>
                  <?php else: ?>
                    <span style="opacity: 0.6; font-style: italic;">(Geral / Sem vínculo)</span>
                  <?php endif; ?>
                </td>
                <td>
                  <code style="background: var(--cor-fundo-terciario); border: 1px solid var(--cor-borda-principal); padding: 3px 6px; font-size: 11px; color: var(--cor-texto-principal); display: inline-block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= htmlspecialchars($t['comando_ou_acao'] ?: 'N/A') ?>
                  </code>
                </td>
                <td>
                  <span style="font-size: 12px; font-weight: bold; font-family: monospace;">
                    ⏱️ <?= htmlspecialchars($t['tempo_estimado'] ?: '—') ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <div class="acoes-tabela" style="justify-content: center;">
                    <button class="btn-acao-icone" 
                            onclick='abrirModalEditar(<?= json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                            title="Editar Teste">Editar</button>
                    <button class="btn-acao-icone excluir" 
                            onclick="excluirRegistro('../api/admin_diagnosticos.php', <?= $t['id'] ?>, '<?= addslashes(htmlspecialchars($t['nome'])) ?>')" 
                            title="Excluir Teste">Excluir</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

  <!-- Modal Adicionar / Editar Teste de Diagnóstico -->
  <div class="modal-admin-overlay" id="modalDiagnosticoAdmin">
    <div class="modal-admin-content" style="max-width: 650px;">
      <span class="fechar-modal" onclick="fecharModalAdmin('modalDiagnosticoAdmin')">&times;</span>
      <h2 class="modal-admin-titulo" id="modalDiagTitulo">Novo Teste de Diagnóstico</h2>

      <form id="formDiagnostico" onsubmit="event.preventDefault(); salvarRegistro('../api/admin_diagnosticos.php', 'formDiagnostico', 'modalDiagnosticoAdmin');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
        <input type="hidden" name="id" id="diag_id">

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
          <div class="form-grupo">
            <label class="form-label" for="nome">Nome do Teste *</label>
            <input type="text" name="nome" id="nome" class="form-input" placeholder="Ex: Teste de Tensão da Fonte (PSU)" required>
          </div>

          <div class="form-grupo">
            <label class="form-label" for="tipo">Categoria *</label>
            <select name="tipo" id="tipo" class="form-select" required>
              <option value="hardware">Hardware</option>
              <option value="software">Software</option>
              <option value="rede">Rede & Conexão</option>
              <option value="seguranca">Segurança & Integridade</option>
              <option value="geral">Geral / Firmware</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
          <div class="form-grupo">
            <label class="form-label" for="componente_id">Componente Vinculado</label>
            <select name="componente_id" id="componente_id" class="form-select">
              <option value="">-- Nenhum / Teste Geral --</option>
              <?php foreach ($todosComponentes as $comp): ?>
                <option value="<?= $comp['id'] ?>">
                  <?= htmlspecialchars($comp['nome']) ?> (<?= ucfirst($comp['tipo']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grupo">
            <label class="form-label" for="icone">Arquivo de Ícone</label>
            <select name="icone" id="icone" class="form-select">
              <option value="cpu.png">cpu.png (Processador / CPU)</option>
              <option value="ram.png">ram.png (Memória RAM)</option>
              <option value="placadevideo.png">placadevideo.png (GPU)</option>
              <option value="fonte4.png">fonte4.png (Fonte de Alimentação)</option>
              <option value="ssd.png">ssd.png (SSD)</option>
              <option value="hd.webp">hd.webp (Disco Rígido HD)</option>
              <option value="placamae.png">placamae.png (Placa-Mãe)</option>
              <option value="cooler.png">cooler.png (Cooler / Fan)</option>
              <option value="bios.png">bios.png (BIOS / UEFI)</option>
              <option value="update.png">update.png (Atualizações)</option>
              <option value="reinstall.png">reinstall.png (Reinstalação SO)</option>
              <option value="Virabot_shell.webp">Virabot_shell.webp (Antivírus / Shell)</option>
              <option value="monitor.png">monitor.png (Monitor / Display)</option>
              <option value="broom.png">broom.png (Limpeza / Manutenção)</option>
              <option value="Setup.png">Setup.png (Setup Geral)</option>
            </select>
          </div>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="descricao">Descrição Técnica / Procedimento *</label>
          <textarea name="descricao" id="descricao" class="form-textarea" placeholder="Descreva brevemente o que esse teste faz e como o técnico opera..." style="min-height: 60px;" required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
          <div class="form-grupo">
            <label class="form-label" for="comando_ou_acao">Comando ou Ação de Sonda</label>
            <input type="text" name="comando_ou_acao" id="comando_ou_acao" class="form-input" placeholder="Ex: multimeter --probe 12v_rail ou memtest86 --passes 4">
          </div>

          <div class="form-grupo">
            <label class="form-label" for="tempo_estimado">Tempo Estimado</label>
            <input type="text" name="tempo_estimado" id="tempo_estimado" class="form-input" placeholder="Ex: 1.2s ou 5 minutos" value="1.0s">
          </div>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="resultado_esperado">Resultado Esperado (Referência Técnica)</label>
          <input type="text" name="resultado_esperado" id="resultado_esperado" class="form-input" placeholder="Ex: Tensões nominais de +12V e +5V com variação máxima de ±5%">
        </div>

        <div class="form-grupo">
          <label class="form-label" for="resultado_normal">Telemetria Saudável (Resultado Normal) *</label>
          <textarea name="resultado_normal" id="resultado_normal" class="form-textarea" placeholder="Texto exibido no terminal quando o componente testado não possui defeito..." style="min-height: 70px;" required></textarea>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="resultado_anomalia">Telemetria de Anomalia (Resultado de Falha) *</label>
          <textarea name="resultado_anomalia" id="resultado_anomalia" class="form-textarea" placeholder="Texto com alerta técnico exibido quando este componente for a causa raiz do defeito ativo..." style="min-height: 70px;" required></textarea>
        </div>

        <div class="form-rodape">
          <button type="button" class="btn-admin-acao" onclick="fecharModalAdmin('modalDiagnosticoAdmin')">Cancelar</button>
          <button type="submit" class="btn-admin-acao btn-admin-destaque">Salvar Teste</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
  <script>
    function abrirModalCriar() {
      document.getElementById('formDiagnostico').reset();
      document.getElementById('diag_id').value = '';
      document.getElementById('modalDiagTitulo').innerText = 'Novo Teste de Diagnóstico';
      document.getElementById('tempo_estimado').value = '1.0s';
      abrirModalAdmin('modalDiagnosticoAdmin');
    }

    function abrirModalEditar(t) {
      document.getElementById('formDiagnostico').reset();
      document.getElementById('diag_id').value = t.id;
      document.getElementById('nome').value = t.nome || '';
      document.getElementById('tipo').value = t.tipo || 'hardware';
      document.getElementById('componente_id').value = t.componente_id || '';
      document.getElementById('icone').value = t.icone || 'cpu.png';
      document.getElementById('descricao').value = t.descricao || '';
      document.getElementById('comando_ou_acao').value = t.comando_ou_acao || '';
      document.getElementById('tempo_estimado').value = t.tempo_estimado || '1.0s';
      document.getElementById('resultado_esperado').value = t.resultado_esperado || '';
      document.getElementById('resultado_normal').value = t.resultado_normal || '';
      document.getElementById('resultado_anomalia').value = t.resultado_anomalia || '';
      document.getElementById('modalDiagTitulo').innerText = 'Editar Teste de Diagnóstico #' + t.id;
      abrirModalAdmin('modalDiagnosticoAdmin');
    }
  </script>
</body>
</html>
