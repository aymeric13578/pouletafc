@extends('admin.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Ajouter une catégorie</span>
</h4>

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations sur la categorie</h5>
        </div>
        <div class="card-body">
            <form action="{{route('storecategory')}}" method="POST" enctype="multipart/form-data">
                @csrf
              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="category_name">Nom catégorie</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="category_name" name="category_name" placeholder="Saisissez le nom de la catégorie" />
                </div>
              </div>
              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="category_code">Code catégorie</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="category_code" name="category_code" placeholder="Saisissez le code de la catégorie" />
                </div>
              </div>

              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="category_code">Image</label>
                <div class="col-sm-10">
                  <input type="file" class="form-control" id="category_image" name="category_image" placeholder="" />
                </div>
              </div>

              <div class="row mb-3">
                
                        <label class=" col-sm-2 col-form-label" for="form-repeater-1-1">Boutique <span
                            class="text-danger">*</span></label>
                       

                        <div class="col-sm-10">
                        <select class="form-select" id="shop_id" name="shop_id" aria-label="Catégorie" required>
                            <option selected>Sélectionnez une boutique</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->{'id'} }}">{{ $shop->{'shop_name'} }}</option>
                            @endforeach
                        </select>
                </div>
                
                    <!-- <div class="col-lg-6">
                        <label class="form-label" for="shop_logo">Logo boutique</label>
                        <input type="file" class="form-control" id="shop_logo" name="shop_logo" />
                    </div> -->
                </div>
              <div class="modal-footer">
                <div class="">
                  <button type="submit" class="btn btn-primary">Ajouter catégorie</button>
                </div>
              </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->

@endsection

