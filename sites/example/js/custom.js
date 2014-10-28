$('.toTop').click(function(){
	scrollto('.page-header');    
});

function scrollto(element){
$('html, body').animate({ scrollTop: (($(element).offset().top)-100)}, 500 );
};