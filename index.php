<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>OGISCA - Chargement</title>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');

  :root {
    --deep-blue: #001f4d;
    --light-blue: #1e90ff;
    --accent-blue: #0077e6;
    --white: #ffffff;
    --light-gray: rgba(255,255,255,0.7);
  }

  * { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
  }

  body, html {
    height: 100%;
    background: linear-gradient(135deg, #000c26 0%, var(--deep-blue) 100%);
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Poppins', sans-serif;
    color: var(--white);
  }

  /* --- Fond animé subtil --- */
  .background-animation {
    position: absolute;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 0;
  }

  .grid-lines {
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: 
      linear-gradient(rgba(30,144,255,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(30,144,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    animation: gridMove 20s linear infinite;
  }

  @keyframes gridMove {
    0% { transform: translate(0, 0); }
    100% { transform: translate(40px, 40px); }
  }

  /* --- Logo au centre --- */
  .splash {
    text-align: center;
    z-index: 10;
    position: relative;
    padding: 2rem;
  }

  .logo-container {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 140px;
    height: 140px;
    margin: 0 auto 2rem;
  }

  .logo-container img {
    width: 120px;
    height: 120px;
    object-fit: contain;
    filter: drop-shadow(0 0 20px rgba(30,144,255,0.6));
    animation: logoAppear 1.5s ease-out forwards;
  }

  @keyframes logoAppear {
    0% { 
      opacity: 0; 
      transform: scale(0.8) rotate(-10deg);
    }
    100% { 
      opacity: 1; 
      transform: scale(1) rotate(0);
    }
  }

  .halo {
    position: absolute;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(30,144,255,0.15) 0%, transparent 70%);
    filter: blur(15px);
    animation: haloPulse 3s ease-in-out infinite;
    z-index: -1;
  }

  @keyframes haloPulse {
    0%, 100% { transform: scale(1); opacity: 0.6; }
    50% { transform: scale(1.2); opacity: 0.3; }
  }

  /* --- Texte OGISCA --- */
  .title {
    margin: 1.5rem 0 2.5rem;
    font-size: 2.8rem;
    font-weight: 600;
    letter-spacing: 0.3em;
    color: var(--white);
    text-shadow: 0 0 15px rgba(30,144,255,0.7);
    display: inline-block;
  }

  .title span {
    display: inline-block;
    opacity: 0;
    transform: translateY(15px);
    animation: letterRise 0.8s forwards;
  }

  @keyframes letterRise {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* --- Barre de progression --- */
  .progress-container {
    width: 280px;
    height: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    margin: 2rem auto 1rem;
    overflow: hidden;
  }

  .progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--light-blue), var(--accent-blue));
    border-radius: 2px;
    animation: progressFill 4.5s ease-in-out forwards;
  }

  @keyframes progressFill {
    0% { width: 0%; }
    100% { width: 100%; }
  }

  /* --- Texte secondaire --- */
  .loading-text {
    margin-top: 1rem;
    font-size: 0.9rem;
    font-weight: 300;
    color: var(--light-gray);
    opacity: 0;
    animation: fadeIn 1s ease forwards 0.5s;
  }

  @keyframes fadeIn { 
    to { opacity: 1; } 
  }

  /* --- Indicateur de statut technique --- */
  .status-indicator {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin-top: 2rem;
    font-size: 0.75rem;
    color: var(--light-gray);
    opacity: 0;
    animation: fadeIn 1s ease forwards 1s;
  }

  .status-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
  }

  .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--light-blue);
    animation: statusPulse 2s infinite;
  }

  @keyframes statusPulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
  }

  /* --- Responsive --- */
  @media (max-width: 480px) {
    .title {
      font-size: 2.2rem;
      letter-spacing: 0.2em;
    }
    
    .progress-container {
      width: 220px;
    }
    
    .status-indicator {
      flex-direction: column;
      gap: 0.5rem;
    }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Animation des lettres "OGISCA"
  const title = document.querySelector('.title');
  const letters = title.textContent.trim().split('');
  title.textContent = '';
  letters.forEach((l, i) => {
    const span = document.createElement('span');
    span.textContent = l;
    span.style.animationDelay = (i * 0.15 + 0.5) + 's';
    title.appendChild(span);
  });

  // Mise à jour du texte de statut
  const statusMessages = [
    "Initialisation du système...",
    "Chargement des modules...",
    "Vérification des connexions...",
    "Préparation de l'interface..."
  ];
  
  const loadingText = document.querySelector('.loading-text');
  let currentStatus = 0;
  
  const statusInterval = setInterval(() => {
    currentStatus = (currentStatus + 1) % statusMessages.length;
    loadingText.textContent = statusMessages[currentStatus];
  }, 1200);

  // Redirection après 5 secondes
  setTimeout(() => {
    clearInterval(statusInterval);
    window.location.href = "login.php";
  }, 5000);
});
</script>
</head>

<body>
  <div class="background-animation">
    <div class="grid-lines"></div>
  </div>

  <div class="splash">
    <div class="logo-container">
      <div class="halo"></div>
      <img src="logo simple sans fond.png" alt="Logo OGISCA">
    </div>

    <div class="title">OGISCA</div>

    <div class="progress-container">
      <div class="progress-bar"></div>
    </div>
    
    <div class="loading-text">Initialisation du système...</div>
    
    <div class="status-indicator">
      <div class="status-item">
        <div class="status-dot"></div>
        <div>Connexion base de données</div>
      </div>
      <div class="status-item">
        <div class="status-dot"></div>
        <div>Chargement modules</div>
      </div>
      <div class="status-item">
        <div class="status-dot"></div>
        <div>Préparation interface</div>
    </div>
  </div>
</body>
</html>