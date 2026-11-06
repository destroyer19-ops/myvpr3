window.Espees = (function () {
  function init(config) {
    const {
      amount,
      sku,
      narration,
      merchant_wallet,
      success_url,
      fail_url,
      token,
      callback_url,
      user_data
    } = config;
    
    const proxy_url = "api/espees_proxy.php";
    const container = document.getElementById("espees-button");
    if (!container) {
      console.error("Espees SDK: no container with id 'espees-button'");
      return;
    }
    
    // Clear previous button if any
    container.innerHTML = '';
    
    const button = document.createElement("button");
    button.innerText = "Pay " + amount + " Espees Instantly";
    const bimag = document.createElement('img')
    bimag.src = 'assets/img/espees-icon.svg'
    bimag.style.width = '30px'
    bimag.style.height = '30px'
    bimag.style.marginLeft = '6px'
    button.appendChild(bimag)
    
    Object.assign(button.style, {
      display: "flex",
      justifyContent: "center",
      alignItems: "center",
      width: "100%",
      fontWeight: "900",
      textAlign: "center",
      whiteSpace: "nowrap",
      verticalAlign: "middle",
      userSelect: "none",
      border: "1px solid transparent",
      padding: "0.775rem 0.75rem",
      fontSize: "1rem",
      lineHeight: "1.5",
      borderRadius: "0.575rem",
      transition: "all 0.15s ease-in-out",
      textDecoration: "none",
      cursor: "pointer",
      color: "#fff",
      backgroundColor: "#250101",
      borderColor: "#250101",
    });

    container.appendChild(button);

    button.addEventListener("click", function () {
      button.disabled = true;
      button.innerText = "Initiating...";
      
      fetch(proxy_url + "?action=initiate", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_sku: sku,
          narration: narration,
          price: amount,
          merchant_wallet: merchant_wallet,
          success_url: success_url,
          fail_url: fail_url,
          token: token,
          callback_url: callback_url,
          user_data: user_data
        })
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.statusCode === 200 && data.payment_ref) {
            window.location.href = `https://payment.espees.org/pay/${data.payment_ref}`;
          } else {
            alert("Payment initiation failed: " + (data.message || "Unknown error"));
            button.disabled = false;
            button.innerText = "Pay " + amount + " Espees Instantly";
            button.appendChild(bimag);
          }
        })
        .catch((err) => {
          console.error("Error contacting proxy:", err);
          alert("Error starting payment.");
          button.disabled = false;
          button.innerText = "Pay " + amount + " Espees Instantly";
          button.appendChild(bimag);
        });
    });
  }

  return { init };
})();
