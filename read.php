<!DOCTYPE html>
<html>
<body>

<h3>Last Image Analysis:</h3>
<div id="analysisText">Detected labels: White, Lens flare</div>

<button id="speakBtn">Read Aloud</button>

<script>
document.getElementById('speakBtn').addEventListener('click', () => {
  const text = document.getElementById('analysisText').textContent;
  if ('speechSynthesis' in window) {
    const utterance = new SpeechSynthesisUtterance(text);
    window.speechSynthesis.speak(utterance);
  } else {
    alert("Sorry, your browser doesn't support speech synthesis.");
  }
});
</script>

</body>
</html>
