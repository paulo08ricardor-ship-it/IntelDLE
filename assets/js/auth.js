async function login() {
  const dados = new FormData();
  dados.append("email", document.querySelector("#email") ? document.querySelector("#email").value : "");
  dados.append("senha", document.querySelector("#senha") ? document.querySelector("#senha").value : "");

  try {
    const req = await fetch("../api/login.php", {
      method: "POST",
      body: dados
    });
    const json = await req.json();
    if (json.sucesso) {
      location.reload();
    } else {
      alert(json.erro || "Login inválido");
    }
  } catch (e) {
    alert("Falha na comunicação.");
  }
}
