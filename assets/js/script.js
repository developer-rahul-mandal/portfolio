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

var navBtn = document.querySelector(".navBtn");
var menu = document.querySelector("nav")
navBtn.addEventListener("click",()=>{
  if (menu.classList.contains("active")) {
    menu.classList.remove("active")
  } else {
    menu.classList.add("active")
  }
})