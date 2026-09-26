window.onscroll = function () {
  moveProgressBar();
};

function moveProgressBar() {
  const winScroll =
    document.body.scrollTop || document.documentElement.scrollTop;
  const height =
    document.documentElement.scrollHeight -
    document.documentElement.clientHeight;

  const scrolled = (winScroll / height) * 100;

  document.getElementById("myProgressBar").style.width = scrolled + "%";
}
