@extends('merchand.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Mise à jour produit</span>
</h4>

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations produit</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('updateproduct') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="id" value="{{ $productInfo->{'id'} }}">
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="designation_tech">Désignation produit<span
                            class="text-danger">*</span></label></label>
                        <input type="text" class="form-control" id="designation_tech" placeholder="Désignation article" name="designation_tech" aria-label="Désignation article" value="{{ $productInfo->{'designation_tech'} }}">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="qty">Quantité <span
                            class="text-danger">*</span></label></label>
                        <input type="number" class="form-control" id="quantity" placeholder="Quantité" name="quantity" aria-label="Quantité" value="{{ $productInfo->{'quantity'} }}">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="price">Prix unitaire <span
                            class="text-danger">*</span></label></label>
                        <input type="number" class="form-control" id="unit_price" placeholder="Prix" name="unit_price" aria-label="Prix" value="{{ $productInfo->{'unit_price'} }}">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="locality">Localité</label>
                        <input type="text" class="form-control" id="locality" placeholder="Désignation article" name="locality" aria-label="Localité" value="{{ $productInfo->{'locality'} }}">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="bar_code">Code bar</label>
                        <input type="text" class="form-control" id="bar_code" placeholder="Code bar" name="bar_code" aria-label="Code bar" value="{{ $productInfo->{'bar_code'} }}">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="form-repeater-1-1">Catégorie <span
                            class="text-danger">*</span></label></label>
                        <select class="form-select" id="category" name="category_id" aria-label="Catégorie">
                            <option value="{{ $productInfo->{'category'}->{'id'} }}" >{{ $productInfo->{'category'}->{'category_name'} }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->{'id'} }}">{{ $category->{'category_name'} }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="form-repeater-1-1">Boutique <span
                            class="text-danger">*</span></label></label>
                        <select class="form-select" id="shop_id" name="shop_id" aria-label="Catégorie">
                            <option value="{{ $productInfo->{'shop'}->{'id'} }}">S{{ $productInfo->{'shop'}->{'shop_name'} }}</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->{'id'} }}">{{ $shop->{'shop_name'} }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="bar_code">Commission</label>
                        <input type="text" class="form-control" id="commission" placeholder="Marque" name="commission" aria-label="Commission" value="{{ $productInfo->{'commission'} }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_length">Longueur produit</label>
                        <input type="text" class="form-control" id="product_length" name="product_length" placeholder="Longueur article" value="{{ $productInfo->{'product_length'} }}" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_width">Largeur produit </label>
                        <input type="text" class="form-control" id="product_width" name="product_width" placeholder="Largeur article" value="{{ $productInfo->{'product_width'} }}" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_epaisseur">Epaisseur produit</label>
                        <input type="text" class="form-control" id="product_epaisseur" name="product_epaisseur" placeholder="Epaisseur article" value="{{ $productInfo->{'product_epaisseur'} }}" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_volume">Volume produit </label>
                        <input type="text" class="form-control" id="product_volume" name="product_volume" placeholder="Volume article" value="{{ $productInfo->{'product_volume'} }}" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_color">Couleur produit</label>
                        <input type="text" class="form-control" id="product_color" name="product_color" placeholder="Couleur article" value="{{ $productInfo->{'product_color'} }}" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_weigth">Poids produit </label>
                        <input type="text" class="form-control" id="product_weigth" name="product_weigth" placeholder="Poids article" value="{{ $productInfo->{'product_weigth'} }}" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="parameter1">Paramètre 1 produit</label>
                        <input type="text" class="form-control" id="parameter1" name="parameter1" placeholder="Paramètre 1 article" value="{{ $productInfo->{'parameter1'} }}" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="parameter2">Paramètre 2 produit </label>
                        <input type="text" class="form-control" id="parameter2" name="parameter2" placeholder="Paramètre 2 article" value="{{ $productInfo->{'parameter2'} }}" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col lg-6">
                        <label class="form-label" for="description">Description article <span
                            class="text-danger">*</span></label></label>
                        <textarea class="form-control" name="description" id="description" cols="10" rows="2">{{ $productInfo->{'description'} }}</textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="">
                        <button type="submit" class="btn btn-primary">Modifier produit</button>
                    </div>
                    </div>
            </form>
        </div>

    </div>
</div>
<!-- / Content -->

@endsection

