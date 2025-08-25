<script>
(function () {
  const second = 1000, minute = second * 60, hour = minute * 60, day = hour * 24;

  // EDIT THIS LINE ONLY (month is 0-based: 8 = September)
  const eventDate = new Date(2025, 8, 21, 18, 0, 0); // Sep 21, 2025 18:00 LOCAL time
  // Or, if you prefer explicit WAT time regardless of viewer’s timezone:
  // const eventDate = new Date('2025-09-21T18:00:00+01:00');

  const target = eventDate.getTime();

  const timer = setInterval(function () {
    const now = Date.now();
    const distance = target - now;

    if (distance <= 0) {
      ["days","hours","minutes","seconds"].forEach(id => document.getElementById(id).innerText = "0");
      document.getElementById("countdown").style.display = "none";
      clearInterval(timer);
      return;
    }

    document.getElementById("days").innerText = Math.floor(distance / day);
    document.getElementById("hours").innerText = Math.floor((distance % day) / hour);
    document.getElementById("minutes").innerText = Math.floor((distance % hour) / minute);
    document.getElementById("seconds").innerText = Math.floor((distance % minute) / second);
  }, 1000);
})();
</script>
