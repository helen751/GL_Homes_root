<!DOCTYPE html>
<html>
<body>
<h3>Last Image Analysis:</h3>
<div id="analysisText">Loading...</div>

<script>
async function readLastAnalysis() {
  try {
    const res = await fetch('read_last_analysis.php');  // your PHP endpoint that returns the sentence JSON
    if (res.status === 204) {
      document.getElementById('analysisText').textContent = "No recent analysis to read.";
      return;
    }
    if (!res.ok) throw new Error("Failed to fetch analysis.");

    const data = await res.json();
    const sentence = data.sentence;
    document.getElementById('analysisText').textContent = sentence;

    // Check if browser supports speech synthesis
    if ('speechSynthesis' in window) {
      const utterance = new SpeechSynthesisUtterance(sentence);
      // Optional: set voice, pitch, rate if you want
      // utterance.voice = speechSynthesis.getVoices()[0];
      // utterance.rate = 1;
      window.speechSynthesis.speak(utterance);
    } else {
      console.warn("Speech Synthesis not supported");
    }
  } catch (error) {
    document.getElementById('analysisText').textContent = "Error: " + error.message;
  }
}

readLastAnalysis();
</script>
</body>
</html>
