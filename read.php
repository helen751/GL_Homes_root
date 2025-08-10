<script>
  let lastSentence = '';
  let userInteracted = false;
  let hasWelcomed = false;

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

        // Speak only after interaction + welcome message has been played
        if (userInteracted && hasWelcomed) {
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

      // Say welcome first, then start analysis reading
      if (!hasWelcomed) {
        speakText("Welcome, IRIS is now active!");
        hasWelcomed = true;
      }

      window.removeEventListener('click', onInteraction);
      window.removeEventListener('keydown', onInteraction);
    }
    window.addEventListener('click', onInteraction);
    window.addEventListener('keydown', onInteraction);
  }

  waitForUserInteraction();

  // Poll the server every 1 second
  setInterval(fetchAnalysis, 1000);
</script>
