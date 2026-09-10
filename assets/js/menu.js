let cadastro = false;

function abrirModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.style.display = "flex";
}

function fecharModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.style.display = "none";
}

window.onclick = function (e) {
  if (e.target && e.target.classList.contains('modal-overlay')) {
    e.target.style.display = "none";
  }
}

function toggleModo() {
  cadastro = !cadastro;

  const elNome = document.getElementById("nome");
  const elTitulo = document.getElementById("modalTitulo");
  const elToggle = document.getElementById("toggleTexto");
  const elBotao = document.getElementById("btnAuthSubmit");

  if (elNome) elNome.style.display = cadastro ? "block" : "none";
  if (elTitulo) elTitulo.innerText = cadastro ? "Cadastro de Jogador" : "Login";
  if (elBotao) elBotao.innerText = cadastro ? "Cadastrar" : "Entrar";

  if (elToggle) {
    elToggle.innerHTML = cadastro
      ? "Já possui conta? <span class='destaque-link'>Faça Login</span>"
      : "Ainda não tem conta? <span class='destaque-link'>Cadastre-se</span>";
  }

  mostrarErro("");
}

function mostrarErro(msg) {
  const el = document.getElementById("erroLogin");
  if (el) {
    el.innerText = msg;
    el.style.display = msg ? "block" : "none";
  }
}

async function enviarFormulario() {
  mostrarErro("");

  const url = cadastro ? "../api/cadastro.php" : "../api/login.php";
  const dados = new FormData();

  if (cadastro) {
    const nomeVal = document.getElementById("nome") ? document.getElementById("nome").value : "";
    dados.append("nome", nomeVal);
  }

  const emailVal = document.getElementById("email") ? document.getElementById("email").value : "";
  const senhaVal = document.getElementById("senha") ? document.getElementById("senha").value : "";

  dados.append("email", emailVal);
  dados.append("senha", senhaVal);

  try {
    const resposta = await fetch(url, {
      method: "POST",
      body: dados
    });

    const json = await resposta.json();

    if (json.sucesso) {
      location.reload();
    } else {
      mostrarErro(json.erro || "Erro na autenticação.");
    }
  } catch (err) {
    mostrarErro("Falha ao comunicar com o servidor.");
  }
}
