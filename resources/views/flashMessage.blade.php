
<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
@if ( session()->has('alert') )
 <div class="">
<script> swal("Erreur","<?= html_entity_decode(session()->get('alert'), ENT_QUOTES, 'UTF-8') ?>", "error") </script>
  </div>
@endif
 
@if ( session()->has('good') )
<div class="">
<script> swal("Good","<?= html_entity_decode(session()->get('good'), ENT_QUOTES, 'UTF-8') ?>", "success") </script>
</div>
@endif

@if ( session()->has('bienvenue') )
<div class="">
<script> swal("Welcome","<?= html_entity_decode(session()->get('bienvenue'), ENT_QUOTES, 'UTF-8') ?>", "success") </script>
</div>
@endif

@if ( session()->has('logout') )
<div class="">
<script> swal("Goodbye","<?= html_entity_decode(session()->get('logout'), ENT_QUOTES, 'UTF-8') ?>", "success") </script>
</div>
@endif


@if ( session()->has('publie') )
<div class="">
<script> swal("Good","<?= html_entity_decode(session()->get('publie'), ENT_QUOTES, 'UTF-8') ?>", "success") </script>
</div>
@endif


@if ( session()->has('merci') )
<div class="">
<script> swal("Merci!!!","<?= html_entity_decode(session()->get('merci'), ENT_QUOTES, 'UTF-8') ?>", "success") </script>
</div>
@endif



<!-- @if ( session()->has('message') )
<div class="">
<script> swal("Message","<?= html_entity_decode(session()->get('message'), ENT_QUOTES, 'UTF-8') ?>", "error") </script>
</div>
@endif -->

