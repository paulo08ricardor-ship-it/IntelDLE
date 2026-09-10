<?php
require_once __DIR__ . '/auth_check.php';

$db = (new Database())->conectar();

$stmt = $db->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM solucoes s WHERE s.defeito_id = d.id) AS total_solucoes,
           (SELECT COUNT(*) FROM solucoes s WHERE s.defeito_id = d.id AND s.correto = 1) AS solucoes_corretas
    FROM defeitos d 
    ORDER BY d.id ASC
");
$defeitos = $stmt->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(obterCsrfToken()) ?>">
  <title>Defeitos - Inteldle Admin</title>
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
    <a href="defeitos.php" class="admin-nav-tab active">Defeitos</a>
    <a href="componentes.php" class="admin-nav-tab">Componentes</a>
    <a href="solucoes.php" class="admin-nav-tab">Soluções</a>
    <a href="diagnosticos.php" class="admin-nav-tab">Diagnósticos</a>
    <a href="usuarios.php" class="admin-nav-tab">Usuários & Permissões</a>
  </nav>

  <!-- Container -->
  <main class="admin-container">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao">Gerenciamento de Defeitos</h1>
        <div class="admin-subtitulo-secao">
          Crie, edite ou remova os cenários de defeitos apresentados aos jogadores.
        </div>
      </div>
      <button class="btn-admin-acao btn-admin-destaque" onclick="abrirModalCriar()">+ Novo Defeito</button>
    </div>

    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th style="width: 70px;">Imagem</th>
            <th>Título</th>
            <th>Descrição</th>
            <th style="width: 100px;">Tipo</th>
            <th style="width: 110px;">Soluções</th>
            <th style="width: 140px; text-align: center;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($defeitos)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 30px; opacity: 0.7;">
                Nenhum defeito cadastrado. Clique em "+ Novo Defeito" para adicionar o primeiro.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($defeitos as $d): ?>
              <tr>
                <td><strong>#<?= $d['id'] ?></strong></td>
                <td>
                  <img src="../assets/imagens/<?= htmlspecialchars($d['imagem'] ?: 'Setup.png') ?>" 
                       alt="Thumb" 
                       class="thumb-tabela"
                       onerror="this.src='../assets/imagens/Setup.png'">
                </td>
                <td><strong><?= htmlspecialchars($d['titulo']) ?></strong></td>
                <td style="font-size: 13px; max-width: 380px;"><?= nl2br(htmlspecialchars($d['descricao'])) ?></td>
                <td>
                  <span class="badge-status <?= $d['tipo'] === 'hardware' ? 'badge-hardware' : 'badge-software' ?>">
                    <?= ucfirst($d['tipo']) ?>
                  </span>
                </td>
                <td>
                  <span title="Total de opções vinculadas / soluções corretas">
                    <?= $d['total_solucoes'] ?> (<?= $d['solucoes_corretas'] ?> ✓)
                  </span>
                </td>
                <td style="text-align: center;">
                  <div class="acoes-tabela" style="justify-content: center;">
                    <button class="btn-acao-icone" 
                            onclick='abrirModalEditar(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                            title="Editar">Editar</button>
                    <a href="solucoes.php?defeito_id=<?= $d['id'] ?>" class="btn-acao-icone" title="Gerenciar Soluções">Soluções</a>
                    <button class="btn-acao-icone excluir" 
                            onclick="excluirRegistro('../api/admin_defeitos.php', <?= $d['id'] ?>, '<?= addslashes(htmlspecialchars($d['titulo'])) ?>')" 
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

  <!-- Modal Adicionar / Editar Defeito -->
  <div class="modal-admin-overlay" id="modalDefeito">
    <div class="modal-admin-content">
      <span class="fechar-modal" onclick="fecharModalAdmin('modalDefeito')">&times;</span>
      <h2 class="modal-admin-titulo" id="modalDefeitoTitulo">Novo Defeito</h2>

      <form id="formDefeito" onsubmit="event.preventDefault(); salvarRegistro('../api/admin_defeitos.php', 'formDefeito', 'modalDefeito');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
        <input type="hidden" name="id" id="defeito_id">

        <div class="form-grupo">
          <label class="form-label" for="titulo">Título do Defeito</label>
          <input type="text" name="titulo" id="titulo" class="form-input" placeholder="Ex: Computador não liga" required>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="descricao">Descrição / Sintomas Detalhados</label>
          <textarea name="descricao" id="descricao" class="form-textarea" placeholder="Descreva os sintomas que o jogador irá ler..." required></textarea>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="tipo">Categoria Principal</label>
          <select name="tipo" id="tipo" class="form-select" required>
            <option value="hardware">Hardware (Exige desligar)</option>
            <option value="software">Software (Exige ligar)</option>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="imagem">Arquivo de Imagem</label>
          <select name="imagem" id="imagem" class="form-select">
            <option value="monitor.png">monitor.png (Padrão)</option>
            <option value="pc1.png">pc1.png</option>
            <option value="pc2.png">pc2.png</option>
            <option value="pc3.png">pc3.png</option>
            <option value="pc4.png">pc4.png</option>
            <option value="Setup.png">Setup.png</option>
            <option value="cpu.png">cpu.png</option>
            <option value="fonte4.png">fonte4.png</option>
            <option value="placamae.png">placamae.png</option>
          </select>
        </div>

        <div class="form-rodape">
          <button type="button" class="btn-admin-acao" onclick="fecharModalAdmin('modalDefeito')">Cancelar</button>
          <button type="submit" class="btn-admin-acao btn-admin-destaque">Salvar Defeito</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
  <script>
    function abrirModalCriar() {
      document.getElementById('formDefeito').reset();
      document.getElementById('defeito_id').value = '';
      document.getElementById('modalDefeitoTitulo').innerText = 'Novo Defeito';
      abrirModalAdmin('modalDefeito');
    }

    function abrirModalEditar(d) {
      document.getElementById('formDefeito').reset();
      document.getElementById('defeito_id').value = d.id;
      document.getElementById('titulo').value = d.titulo;
      document.getElementById('descricao').value = d.descricao;
      document.getElementById('tipo').value = d.tipo;
      document.getElementById('imagem').value = d.imagem || 'monitor.png';
      document.getElementById('modalDefeitoTitulo').innerText = 'Editar Defeito #' + d.id;
      abrirModalAdmin('modalDefeito');
    }
  </script>
</body>
</html>
