@extends('admin.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Ajouter un article</span>
</h4>

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations sur l'article</h5>
        </div>
        <div class="card-body">
            <form action="{{route('storeArticle')}}" method="POST" enctype="multipart/form-data">
                @csrf
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="category_name">Title *</label>
                    <input type="text" required class="form-control" id="shop_name" name="title" placeholder="Titre" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="shop_logo">Banniere * (358 x 200 pixels)</label>
                    <input type="file" required class="form-control" id="shop_logo" name="image" />
                </div>

              </div>

              <div class="row mb-3">
                    <div class="col lg-6">
                        <label class="form-label" for="description">Description <span
                            class="text-danger"></span></label></label>
                        <textarea  required class="form-control" name="description" id="description" cols="10" rows="2"></textarea>
                    </div>
                </div>


              <div class="modal-footer">
                <div class="">
                  <button type="submit" class="btn btn-primary">Valider</button>
                </div>
              </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->

@endsection

