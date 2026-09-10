<?php
require_once __DIR__ . '/auth_check.php';

$db = (new Database())->conectar();

$filtroDefeito = filter_var($_GET['defeito_id'] ?? null, FILTER_VALIDATE_INT);

// Lista de todos os defeitos para o filtro e select
$todosDefeitos = $db->query("SELECT id, titulo FROM defeitos ORDER BY id ASC")->fetchAll();
// Lista de todos os componentes para o select
$todosComponentes = $db->query("SELECT id, nome, tipo, icone FROM componentes ORDER BY tipo ASC, nome ASC")->fetchAll();

if ($filtroDefeito) {
    $stmt = $db->prepare("
        SELECT s.*, c.nome AS componente_nome, c.tipo AS componente_tipo, c.icone AS componente_icone, d.titulo AS defeito_titulo
        FROM solucoes s
        INNER JOIN componentes c ON c.id = s.componente_id
        INNER JOIN defeitos d ON d.id = s.defeito_id
        WHERE s.defeito_id = ?
        ORDER BY s.correto DESC, c.nome ASC
    ");
    $stmt->execute([$filtroDefeito]);
} else {
    $stmt = $db->query("
        SELECT s.*, c.nome AS componente_nome, c.tipo AS componente_tipo, c.icone AS componente_icone, d.titulo AS defeito_titulo
        FROM solucoes s
        INNER JOIN componentes c ON c.id = s.componente_id
        INNER JOIN defeitos d ON d.id = s.defeito_id
        ORDER BY d.id ASC, s.correto DESC, c.nome ASC
    ");
}
$solucoes = $stmt->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(obterCsrfToken()) ?>">
  <title>Soluções - Inteldle Admin</title>
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
    <a href="solucoes.php" class="admin-nav-tab active">Soluções</a>
    <a href="diagnosticos.php" class="admin-nav-tab">Diagnósticos</a>
    <a href="usuarios.php" class="admin-nav-tab">Usuários & Permissões</a>
  </nav>

  <!-- Container -->
  <main class="admin-container">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao">Gerenciamento de Soluções</h1>
        <div class="admin-subtitulo-secao">
          Tabela relacional associando Defeitos e Componentes com mensagens explicativas de feedback.
        </div>
      </div>
      <div style="display: flex; gap: 10px; align-items: center;">
        <form method="GET" action="solucoes.php" style="margin: 0;">
          <select name="defeito_id" class="form-select" onchange="this.form.submit()" style="padding: 8px 12px; font-size: 13px;">
            <option value="">-- Todos os Defeitos --</option>
            <?php foreach ($todosDefeitos as $def): ?>
              <option value="<?= $def['id'] ?>" <?= $filtroDefeito == $def['id'] ? 'selected' : '' ?>>
                Defeito #<?= $def['id'] ?>: <?= htmlspecialchars($def['titulo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
        <button class="btn-admin-acao btn-admin-destaque" onclick="abrirModalCriar()">+ Nova Solução</button>
      </div>
    </div>

    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th>Defeito Vinculado</th>
            <th>Componente Testado</th>
            <th style="width: 110px;">Status</th>
            <th>Mensagem de Diagnóstico</th>
            <th style="width: 120px; text-align: center;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($solucoes)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 30px; opacity: 0.7;">
                Nenhuma solução encontrada para os critérios selecionados.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($solucoes as $s): ?>
              <tr>
                <td><strong>#<?= $s['id'] ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($s['defeito_titulo']) ?></strong>
                  <span style="font-size: 11px; opacity: 0.7; display: block;">(#<?= $s['defeito_id'] ?>)</span>
                </td>
                <td>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <img src="../assets/imagens/<?= htmlspecialchars($s['componente_icone'] ?: 'cpu.png') ?>" 
                         alt="Ícone" 
                         style="width: 24px; height: 24px; object-fit: contain;">
                    <strong><?= htmlspecialchars($s['componente_nome']) ?></strong>
                  </div>
                </td>
                <td>
                  <?php if ((int)$s['correto'] === 1): ?>
                    <span class="badge-status badge-correto">✓ Solução</span>
                  <?php else: ?>
                    <span class="badge-status badge-incorreto">✗ Erro</span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 13px; max-width: 400px;"><?= nl2br(htmlspecialchars($s['mensagem'])) ?></td>
                <td style="text-align: center;">
                  <div class="acoes-tabela" style="justify-content: center;">
                    <button class="btn-acao-icone" 
                            onclick='abrirModalEditar(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                            title="Editar">Editar</button>
                    <button class="btn-acao-icone excluir" 
                            onclick="excluirRegistro('../api/admin_solucoes.php', <?= $s['id'] ?>, 'Solução #<?= $s['id'] ?>')" 
                            title="Excluir">Excluir</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

  <!-- Modal Adicionar / Editar Solução -->
  <div class="modal-admin-overlay" id="modalSolucao">
    <div class="modal-admin-content">
      <span class="fechar-modal" onclick="fecharModalAdmin('modalSolucao')">&times;</span>
      <h2 class="modal-admin-titulo" id="modalSolTitulo">Nova Solução</h2>

      <form id="formSolucao" onsubmit="event.preventDefault(); salvarRegistro('../api/admin_solucoes.php', 'formSolucao', 'modalSolucao');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
        <input type="hidden" name="id" id="sol_id">

        <div class="form-grupo">
          <label class="form-label" for="defeito_id">Defeito</label>
          <select name="defeito_id" id="defeito_id" class="form-select" required>
            <option value="">-- Selecione o Defeito --</option>
            <?php foreach ($todosDefeitos as $def): ?>
              <option value="<?= $def['id'] ?>">#<?= $def['id'] ?> - <?= htmlspecialchars($def['titulo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="componente_id">Componente</label>
          <select name="componente_id" id="componente_id" class="form-select" required>
            <option value="">-- Selecione o Componente --</option>
            <?php foreach ($todosComponentes as $comp): ?>
              <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['nome']) ?> (<?= ucfirst($comp['tipo']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="correto">Resultado da Ação</label>
          <select name="correto" id="correto" class="form-select" required>
            <option value="1">✓ Correto (Resolve este Defeito)</option>
            <option value="0">✗ Incorreto (Não é a causa)</option>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="mensagem">Mensagem / Diagnóstico Explicativo</label>
          <textarea name="mensagem" id="mensagem" class="form-textarea" placeholder="Ex: Ao substituir o componente X o defeito foi solucionado..." required></textarea>
        </div>

        <div class="form-rodape">
          <button type="button" class="btn-admin-acao" onclick="fecharModalAdmin('modalSolucao')">Cancelar</button>
          <button type="submit" class="btn-admin-acao btn-admin-destaque">Salvar Solução</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
  <script>
    function abrirModalCriar() {
      document.getElementById('formSolucao').reset();
      document.getElementById('sol_id').value = '';
      <?php if ($filtroDefeito): ?>
        document.getElementById('defeito_id').value = '<?= $filtroDefeito ?>';
      <?php endif; ?>
      document.getElementById('modalSolTitulo').innerText = 'Nova Solução';
      abrirModalAdmin('modalSolucao');
    }

    function abrirModalEditar(s) {
      document.getElementById('formSolucao').reset();
      document.getElementById('sol_id').value = s.id;
      document.getElementById('defeito_id').value = s.defeito_id;
      document.getElementById('componente_id').value = s.componente_id;
      document.getElementById('correto').value = s.correto;
      document.getElementById('mensagem').value = s.mensagem;
      document.getElementById('modalSolTitulo').innerText = 'Editar Solução #' + s.id;
      abrirModalAdmin('modalSolucao');
    }
  </script>
</body>
</html>
