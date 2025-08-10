<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Iris Detection Reader</title>
  <!-- Bootstrap CSS CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      padding-top: 2rem;
      background: #f8f9fa;
    }
    .iris-img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 0 auto 2rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    #analysisText {
      font-size: 1.3rem;
      font-weight: 500;
      color: #212529;
      min-height: 3rem;
    }
  </style>
</head>
<body>
  <div class="container text-center">
    <img src="iris.jpg" alt="Iris Image" class="iris-img" />
    <h1 class="mb-4">Iris Image Analysis</h1>
    <div class="card p-4 shadow-sm bg-white">
      <h4>Last Image Analysis:</h4>
      <div id="analysisText" class="mt-3">Loading...</div>
    </div>
  </div>

  <script>
    let lastSentence = '';
    let userInteracted = false;

    // Speak text with Web Speech API
    function speakText(text) {
      if ('speechSynthesis' in window) {
        const utterance = new SpeechSynthesisUtterance(text);
        window.speechSynthesis.speak(utterance);
      }
    }

    // Fetch the latest analysis from your PHP backend
    async function fetchAnalysis() {
      try {
        const res = await fetch('read_last_analysis.php');
        if (res.status === 204) return; // No recent content
        if (!res.ok) throw new Error('Failed to fetch analysis.');
        const data = await res.json();

        // Replace "Detected labels:" with "Be careful, I see"
        let sentence = data.sentence.replace(/^Detected labels:/i, 'Be careful, I see');

        if (sentence !== lastSentence) {
          lastSentence = sentence;
          document.getElementById('analysisText').textContent = sentence;

          // Only speak after user interaction (browser autoplay policy)
          if (userInteracted) {
            speakText(sentence);
          }
        }
      } catch (err) {
        console.error('Error fetching analysis:', err);
        document.getElementById('analysisText').textContent = 'Error loading analysis';
      }
    }

    // Wait for first user interaction to allow speech
    function waitForUserInteraction() {
      function onInteraction() {
        userInteracted = true;
        if (lastSentence) {
          speakText(lastSentence);
        }
        window.removeEventListener('click', onInteraction);
        window.removeEventListener('keydown', onInteraction);
      }
      window.addEventListener('click', onInteraction);
      window.addEventListener('keydown', onInteraction);
    }

    waitForUserInteraction();

    // Poll the server every 2 seconds
    fetchAnalysis(); // initial fetch
    setInterval(fetchAnalysis, 2000);
  </script>

  <!-- Bootstrap JS (optional, for components) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
