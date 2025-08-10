<!DOCTYPE html>
<html>
<body>
<h2>Last Image Analysis</h2>
<div id="analysisText">Loading...</div>

<script>
async function readLastAnalysis() {
  try {
    const res = await fetch('read_last_analysis.php');
    if (res.status === 204) {
      document.getElementById('analysisText').textContent = "No recent analysis to read.";
      return;
    }
    if (!res.ok) throw new Error("Failed to fetch");

    const data = await res.json();
    const sentence = data.sentence;
    document.getElementById('analysisText').textContent = sentence;

    // Use Web Speech API to read the sentence aloud
    const utterance = new SpeechSynthesisUtterance(sentence);
    window.speechSynthesis.speak(utterance);
  } catch (e) {
    document.getElementById('analysisText').textContent = "Error: " + e.message;
  }
}

readLastAnalysis();
</script>
</body>
</html>
