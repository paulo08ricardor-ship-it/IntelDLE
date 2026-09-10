<?php
require_once __DIR__ . '/../config/session.php';
$temaAtual = $_SESSION['tema'] ?? $_COOKIE['inteldle_tema'] ?? 'escuro';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Como Jogar - Inteldle</title>
  
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <script src="../assets/js/tema.js"></script>

  <style>
    .conteudo-tutorial {
      background-color: var(--cor-fundo-secundario);
      border: 2px solid var(--cor-borda-principal);
      max-width: 800px;
      padding: 35px;
      margin: 30px auto;
      border-radius: 8px;
      color: var(--cor-texto-destaque);
      line-height: 1.6;
    }
    .passo {
      margin-bottom: 25px;
    }
    .passo-titulo {
      color: var(--cor-texto-principal);
      font-size: 20px;
      font-weight: bold;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .passo-desc {
      font-size: 15px;
      color: inherit;
    }
  </style>
</head>

<body class="<?= $temaAtual === 'claro' ? 'tema-claro' : '' ?>">

  <header>
    <div class="logo">Inteldle</div>

    <div class="controles-header">
      <button class="btn-toggle-tema" onclick="toggleTemaGlobal()" title="Alternar Tema">🌓</button>
      <a href="menu.php" class="botao" style="padding: 8px 18px; font-size: 15px;">← Voltar ao Menu</a>
    </div>
  </header>

  <div class="conteudo-tutorial">
    <h1 style="margin-top: 0; font-size: 32px; color: var(--cor-texto-principal); text-align: center;">📖 Como Jogar o Inteldle</h1>
    
    <div class="passo">
      <div class="passo-titulo">1. Leia os Sintomas do Computador</div>
      <div class="passo-desc">Observe a imagem e a descrição do problema apresentado no painel superior. Cada defeito tem uma causa raiz específica de hardware ou software.</div>
    </div>

    <div class="passo">
      <div class="passo-titulo">2. Regra de Segurança do Botão Power ⚡</div>
      <div class="passo-desc">
        • <strong>Hardware:</strong> Para trocar ou inspecionar peças físicas (RAM, Fonte, CPU, etc.), você <strong>DEVE DESLIGAR</strong> o computador no botão Power.<br>
        • <strong>Software:</strong> Para executar testes lógicos (BIOS, Antivírus, Drivers, etc.), o computador <strong>DEVE ESTAR LIGADO</strong>.
      </div>
    </div>

    <div class="passo">
      <div class="passo-titulo">3. Modos de Jogo</div>
      <div class="passo-desc">
        • <strong>Defeito Diário:</strong> Um desafio por dia com pontuação inicial de 10.000 pontos. Cada tentativa errada penaliza sua pontuação.<br>
        • <strong>Defeitos Infinitos:</strong> Resolva o máximo de problemas em sequência sem errar para registrar o maior recorde de streak!
      </div>
    </div>

    <div style="text-align: center; margin-top: 30px;">
      <a href="diario.php" class="botao" style="display: inline-block;">Jogar Agora →</a>
    </div>
  </div>

</body>
</html>
