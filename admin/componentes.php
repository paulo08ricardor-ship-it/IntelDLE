<?php
require_once __DIR__ . '/auth_check.php';

$db = (new Database())->conectar();

$stmt = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM solucoes s WHERE s.componente_id = c.id) AS total_vinculos
    FROM componentes c 
    ORDER BY c.tipo ASC, c.nome ASC
");
$componentes = $stmt->fetchAll();

$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(obterCsrfToken()) ?>">
  <title>Componentes - Inteldle Admin</title>
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
    <a href="componentes.php" class="admin-nav-tab active">Componentes</a>
    <a href="solucoes.php" class="admin-nav-tab">Soluções</a>
    <a href="diagnosticos.php" class="admin-nav-tab">Diagnósticos</a>
    <a href="usuarios.php" class="admin-nav-tab">Usuários & Permissões</a>
  </nav>

  <!-- Container -->
  <main class="admin-container">
    
    <div class="admin-header-secao">
      <div>
        <h1 class="admin-titulo-secao">Catálogo de Componentes</h1>
        <div class="admin-subtitulo-secao">
          Peças de hardware e ferramentas de software normalizadas em 3FN disponíveis para os técnicos.
        </div>
      </div>
      <button class="btn-admin-acao btn-admin-destaque" onclick="abrirModalCriar()">+ Novo Componente</button>
    </div>

    <div class="tabela-wrapper">
      <table class="tabela-admin">
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th style="width: 70px;">Ícone</th>
            <th>Nome do Componente</th>
            <th style="width: 110px;">Tipo</th>
            <th>Descrição Técnica</th>
            <th style="width: 130px;">Vínculos em Soluções</th>
            <th style="width: 120px; text-align: center;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($componentes)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 30px; opacity: 0.7;">
                Nenhum componente cadastrado.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($componentes as $c): ?>
              <tr>
                <td><strong>#<?= $c['id'] ?></strong></td>
                <td>
                  <img src="../assets/imagens/<?= htmlspecialchars($c['icone'] ?: 'cpu.png') ?>" 
                       alt="Ícone" 
                       class="thumb-tabela"
                       onerror="this.src='../assets/imagens/cpu.png'">
                </td>
                <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                <td>
                  <span class="badge-status <?= $c['tipo'] === 'hardware' ? 'badge-hardware' : 'badge-software' ?>">
                    <?= ucfirst($c['tipo']) ?>
                  </span>
                </td>
                <td style="font-size: 13px;"><?= htmlspecialchars($c['descricao'] ?? '—') ?></td>
                <td>
                  <span><?= $c['total_vinculos'] ?> vínculos</span>
                </td>
                <td style="text-align: center;">
                  <div class="acoes-tabela" style="justify-content: center;">
                    <button class="btn-acao-icone" 
                            onclick='abrirModalEditar(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                            title="Editar">Editar</button>
                    <button class="btn-acao-icone excluir" 
                            onclick="excluirRegistro('../api/admin_componentes.php', <?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['nome'])) ?>')" 
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

  <!-- Modal Adicionar / Editar Componente -->
  <div class="modal-admin-overlay" id="modalComponente">
    <div class="modal-admin-content">
      <span class="fechar-modal" onclick="fecharModalAdmin('modalComponente')">&times;</span>
      <h2 class="modal-admin-titulo" id="modalCompTitulo">Novo Componente</h2>

      <form id="formComponente" onsubmit="event.preventDefault(); salvarRegistro('../api/admin_componentes.php', 'formComponente', 'modalComponente');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
        <input type="hidden" name="id" id="comp_id">

        <div class="form-grupo">
          <label class="form-label" for="nome">Nome do Componente</label>
          <input type="text" name="nome" id="nome" class="form-input" placeholder="Ex: GPU, RAM, Fonte, BIOS..." required>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="tipo">Categoria</label>
          <select name="tipo" id="tipo" class="form-select" required>
            <option value="hardware">Hardware</option>
            <option value="software">Software</option>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="icone">Arquivo de Ícone</label>
          <select name="icone" id="icone" class="form-select">
            <option value="cpu.png">cpu.png (CPU / Processador)</option>
            <option value="ram.png">ram.png (Memória RAM)</option>
            <option value="placadevideo.png">placadevideo.png (GPU)</option>
            <option value="hd.webp">hd.webp (Disco Rígido HD)</option>
            <option value="ssd.png">ssd.png (SSD)</option>
            <option value="monitor.png">monitor.png (Monitor)</option>
            <option value="broom.png">broom.png (Limpeza)</option>
            <option value="placamae.png">placamae.png (Placa-Mãe)</option>
            <option value="cooler.png">cooler.png (Cooler)</option>
            <option value="fonte4.png">fonte4.png (Fonte)</option>
            <option value="bios.png">bios.png (BIOS)</option>
            <option value="update.png">update.png (Atualização)</option>
            <option value="reinstall.png">reinstall.png (Reinstalar SO)</option>
            <option value="Virabot_shell.webp">Virabot_shell.webp (Procurar Vírus)</option>
          </select>
        </div>

        <div class="form-grupo">
          <label class="form-label" for="descricao">Descrição Técnica (Opcional)</label>
          <textarea name="descricao" id="descricao" class="form-textarea" placeholder="Breve descrição da função do componente..."></textarea>
        </div>

        <div class="form-rodape">
          <button type="button" class="btn-admin-acao" onclick="fecharModalAdmin('modalComponente')">Cancelar</button>
          <button type="submit" class="btn-admin-acao btn-admin-destaque">Salvar Componente</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
  <script>
    function abrirModalCriar() {
      document.getElementById('formComponente').reset();
      document.getElementById('comp_id').value = '';
      document.getElementById('modalCompTitulo').innerText = 'Novo Componente';
      abrirModalAdmin('modalComponente');
    }

    function abrirModalEditar(c) {
      document.getElementById('formComponente').reset();
      document.getElementById('comp_id').value = c.id;
      document.getElementById('nome').value = c.nome;
      document.getElementById('tipo').value = c.tipo;
      document.getElementById('icone').value = c.icone || 'cpu.png';
      document.getElementById('descricao').value = c.descricao || '';
      document.getElementById('modalCompTitulo').innerText = 'Editar Componente #' + c.id;
      abrirModalAdmin('modalComponente');
    }
  </script>
</body>
</html>
