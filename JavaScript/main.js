/*Cuando el documento este listo ejecutamos una funcion*/
$(document).ready(function () {
  /*ACCEDER AL MENU, A LOS ELEMENTOS LI Q POSEAN UL DENTRO*/
  $(".menu li:has(ul)").click(function (e) {
    /*prevenimos q redirija*/
    e.preventDefault();
    /*Si posee la clase activate lo queremos mostrar*/
    if ($(this).hasClass("activado")) {
      $(this).removeClass("activado");
      $(this).children("ul").slideUp();
    } else {
      $(".menu li ul").slideUp();
      $(".menu li").removeClass("activado");
      $(this).addClass("activado");
      $(this).children("ul").slideDown();
    }
  });

  $(".btn-menu").click(function () {
    $(".contenedor-menu .menu").$(selector).slideToggle();
  });
  $(window).resize(function(){
if($(document).width() > 450){
    $('.contenedor-menu .menu').css({'display' : 'block'});
}
if($(document).width()< 450){
    $('.contenedor-menu .menu').css({'display' : 'none'});
    $('.menu li ul').slideUp();
    $('.menu li ul').removeClass('activado');
}
  });
});
