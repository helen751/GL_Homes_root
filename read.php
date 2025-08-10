<!DOCTYPE html>
<html>
<body>

<h3>Last Image Analysis:</h3>
<div id="analysisText">Loading...</div>

<script>
let lastSentence = '';

// Fetch sentence from server and update UI
async function fetchAnalysis() {
  try {
    const res = await fetch('read_last_analysis.php');
    if (!res.ok) throw new Error("Failed to fetch analysis.");
    const data = await res.json();

    // Replace "Detected labels:" with your custom phrase
    lastSentence = data.sentence.replace(/^Detected labels:/i, "Be careful, I see");
    document.getElementById('analysisText').textContent = lastSentence;
  } catch (err) {
    document.getElementById('analysisText').textContent = 'Error: ' + err.message;
  }
}

// Function to speak text
function speakText(text) {
  if ('speechSynthesis' in window) {
    const utterance = new SpeechSynthesisUtterance(text);
    window.speechSynthesis.speak(utterance);
  } else {
    alert("Sorry, your browser doesn't support speech synthesis.");
  }
}

function waitForUserInteractionAndSpeak() {
  function handler() {
    speakText(lastSentence);
    // Remove listener after first interaction
    window.removeEventListener('click', handler);
    window.removeEventListener('keydown', handler);
  }
  window.addEventListener('click', handler);
  window.addEventListener('keydown', handler);
}

fetchAnalysis().then(() => {
  waitForUserInteractionAndSpeak();
});
</script>

</body>
</html>
