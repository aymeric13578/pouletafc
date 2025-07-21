@extends('merchand.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Modifier Catégorie</span>
</h4>

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations sur la catégorie</h5>
        </div>
        <div class="card-body">
            <form action="{{route('updatecategory')}}" method="POST">
                @csrf
              <input type="hidden" value="{{$category_info->{'id'} }}" name="id">
              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="category_name">Nom catégorie</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="category_name" name="category_name" placeholder="Saisissez le nom de la catégorie" value="{{ $category_info->{'category_name'} }}" />
                </div>
              </div>
              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="category_code">Code catégorie</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="category_code" name="category_code" placeholder="Saisissez le code de la catégorie" value="{{ $category_info->{'category_code'} }}" />
                </div>
              </div>

              <div class="row justify-content-end">
                <div class="col-sm-10">
                  <button type="submit" class="btn btn-primary">Modifier catégorie</button>
                </div>
              </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->

@endsection

