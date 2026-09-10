<?php
/**
 * Componente: Tela de Conclusão do Desafio Diário - Inteldle
 * Exibe o resumo do diagnóstico diário, pontuação obtida, posição no ranking,
 * solução técnica aplicada, relógio de contagem regressiva para a próxima meia-noite
 * e botões de compartilhamento e navegação.
 *
 * Parâmetros esperados ($dadosConclusao array opcional):
 * - pontuacao (int)
 * - posicao (int)
 * - totalJogadores (int)
 * - defeitoTitulo (string)
 * - defeitoImagem (string)
 * - componenteNome (string)
 * - solucaoMensagem (string)
 */

$dadosConclusao = $dadosConclusao ?? [];

// Busca automática no banco caso não tenha sido injetado diretamente no escopo
if (empty($dadosConclusao) && isset($db) && isset($id_usuario) && $id_usuario !== null) {
    try {
        // Busca pontuação e posição no ranking diário de hoje
        $stmtRank = $db->prepare("
            SELECT 
                r.pontuacao,
                (SELECT COUNT(*) + 1 FROM ranking_diario r2 WHERE r2.data_jogo = DATE('now') AND r2.pontuacao > r.pontuacao) AS posicao,
                (SELECT COUNT(*) FROM ranking_diario r3 WHERE r3.data_jogo = DATE('now')) AS total_jogadores
            FROM ranking_diario r
            WHERE r.usuario_id = ? AND r.data_jogo = DATE('now')
            LIMIT 1
        ");
        $stmtRank->execute([$id_usuario]);
        $rankInfo = $stmtRank->fetch(PDO::FETCH_ASSOC);

        // Busca o último defeito diário resolvido hoje pelo usuário
        $stmtHist = $db->prepare("
            SELECT 
                d.titulo AS defeito_titulo, 
                d.imagem AS defeito_imagem, 
                c.nome AS componente_nome, 
                s.mensagem AS solucao_mensagem
            FROM historico h
            INNER JOIN defeitos d ON d.id = h.defeito_id
            LEFT JOIN componentes c ON c.id = h.componente_id
            LEFT JOIN solucoes s ON s.defeito_id = h.defeito_id AND s.componente_id = h.componente_id
            WHERE h.usuario_id = ? AND h.modo = 'diario' AND h.acertou = 1 AND DATE(h.data_partida) = DATE('now')
            ORDER BY h.id DESC
            LIMIT 1
        ");
        $stmtHist->execute([$id_usuario]);
        $histInfo = $stmtHist->fetch(PDO::FETCH_ASSOC);

        if ($rankInfo) {
            $dadosConclusao['pontuacao'] = (int) $rankInfo['pontuacao'];
            $dadosConclusao['posicao'] = (int) $rankInfo['posicao'];
            $dadosConclusao['totalJogadores'] = (int) $rankInfo['total_jogadores'];
        }

        if ($histInfo) {
            $dadosConclusao['defeitoTitulo'] = $histInfo['defeito_titulo'];
            $dadosConclusao['defeitoImagem'] = $histInfo['defeito_imagem'];
            $dadosConclusao['componenteNome'] = $histInfo['componente_nome'];
            $dadosConclusao['solucaoMensagem'] = $histInfo['solucao_mensagem'];
        }
    } catch (Exception $e) {
        error_log("Erro ao carregar dados de conclusao diaria: " . $e->getMessage());
    }
}

// Extração segura das variáveis com fallbacks (suporta snake_case e camelCase)
$pontuacao = (int) ($dadosConclusao['pontuacao'] ?? $pontuacao ?? 10000);
$posicao = (int) ($dadosConclusao['posicao'] ?? $posicao ?? 1);
$totalJogadores = (int) ($dadosConclusao['total_jogadores'] ?? $dadosConclusao['totalJogadores'] ?? $totalJogadores ?? 1);
$defeitoTitulo = (string) ($dadosConclusao['defeito_titulo'] ?? $dadosConclusao['defeitoTitulo'] ?? $defeitoTitulo ?? 'Diagnóstico de Sistema Concluído');
$defeitoImagem = (string) ($dadosConclusao['defeito_imagem'] ?? $dadosConclusao['defeitoImagem'] ?? $defeitoImagem ?? 'monitor.png');
$componenteNome = (string) ($dadosConclusao['componente_correto_nome'] ?? $dadosConclusao['componenteNome'] ?? $componenteNome ?? 'Componente Adequado');
$solucaoMensagem = (string) ($dadosConclusao['solucao_mensagem'] ?? $dadosConclusao['solucaoMensagem'] ?? $solucaoMensagem ?? 'O componente correto foi aplicado e o computador foi restaurado com sucesso para pleno funcionamento.');
?>

<div class="painel-diario-concluido" id="painelDiarioConcluido" data-pontuacao="<?= $pontuacao ?>" data-defeito="<?= htmlspecialchars($defeitoTitulo) ?>">

  <!-- Header / Banner de Conclusão -->
  <div class="concluido-header">
    <div class="concluido-icone" aria-hidden="true">🏆</div>
    <h2 class="concluido-titulo">Desafio Diário Concluído!</h2>
    <p class="concluido-subtitulo">Você já diagnosticou o computador de hoje com sucesso.</p>
  </div>

  <!-- Grade de Estatísticas -->
  <div class="grade-stats-diario">
    <div class="stat-item card-stat">
      <div class="stat-icone">⚡</div>
      <div class="stat-valor" id="statPontuacao"><?= number_format($pontuacao, 0, ',', '.') ?> pts</div>
      <div class="stat-rotulo">Pontuação Obtida</div>
    </div>

    <div class="stat-item card-stat">
      <div class="stat-icone">🥇</div>
      <div class="stat-valor" id="statPosicao">#<?= $posicao ?> de <?= $totalJogadores ?> <?= $totalJogadores === 1 ? 'jogador' : 'jogadores' ?></div>
      <div class="stat-rotulo">Posição no Ranking</div>
    </div>

    <div class="stat-item card-stat">
      <div class="stat-icone">🛡️</div>
      <div class="stat-valor stat-sucesso">✓ Reparado com sucesso</div>
      <div class="stat-rotulo">Status do Sistema</div>
    </div>
  </div>

  <!-- Card de Resumo da Solução Técnica -->
  <div class="card-solucao-diario">
    <div class="solucao-img-box">
      <img src="../assets/imagens/<?= !empty($defeitoImagem) ? htmlspecialchars($defeitoImagem) : 'monitor.png' ?>" 
           alt="<?= htmlspecialchars($defeitoTitulo) ?>" 
           class="solucao-img"
           onerror="this.src='../assets/imagens/monitor.png'">
    </div>

    <div class="solucao-info">
      <div class="solucao-badge-tipo">Diagnóstico Finalizado</div>
      <h3 class="solucao-defeito-titulo"><?= htmlspecialchars($defeitoTitulo) ?></h3>
      
      <div class="solucao-item">
        <span class="solucao-rotulo">🔧 Componente Utilizado:</span>
        <span class="badge-componente"><?= htmlspecialchars($componenteNome) ?></span>
      </div>

      <div class="solucao-item solucao-item-desc">
        <span class="solucao-rotulo">📋 Solução Técnica:</span>
        <p class="solucao-descricao"><?= htmlspecialchars($solucaoMensagem) ?></p>
      </div>
    </div>
  </div>

  <!-- Box do Contador Regressivo -->
  <div class="box-contador-diario">
    <div class="contador-rotulo">⏳ Próximo Desafio Diário em:</div>
    <div class="contador-regressivo" id="contadorRegressivoDiario">
      <span class="tempo-digito" id="cdHoras">00</span>h 
      <span class="tempo-digito" id="cdMinutos">00</span>m 
      <span class="tempo-digito" id="cdSegundos">00</span>s
    </div>
    <div class="contador-dica">Um novo cenário de diagnóstico é liberado diariamente à meia-noite (00:00:00).</div>
  </div>

  <!-- Botões de Ação -->
  <div class="botoes-acao-concluido">
    <button type="button" class="btn-compartilhar" onclick="compartilharResultadoDiario(<?= $pontuacao ?>, '<?= htmlspecialchars(addslashes($defeitoTitulo), ENT_QUOTES, 'UTF-8') ?>')">
      📋 Copiar Resultado
    </button>
    <a href="infinito.php" class="btn-acao-destaque">🚀 Jogar Modo Infinito</a>
    <a href="perfil.php" class="btn-acao-secundario">👤 Ver Meu Perfil</a>
    <a href="menu.php" class="btn-acao-secundario">🏠 Voltar ao Menu</a>
  </div>

  <!-- Elemento de Notificação Toast -->
  <div id="toastNotificacao" class="toast-notificacao" role="status" aria-live="polite"></div>

</div>
