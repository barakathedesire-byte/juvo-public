@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.product_reviews.edit') }}</h6>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.reviews.update', $review->id) }}" method="POST">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-3">
                <label class="form-label">{{ __('cms.product_reviews.customer_name') }}</label>
                <input type="text" class="form-control" value="{{ optional($review->customer)->name ?? 'Guest' }}" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label">{{ __('cms.product_reviews.rating') }}</label>
                <select name="rating" class="form-select">
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected((int) old('rating', $review->rating) === $i)>{{ $i }}</option>
                    @endfor
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">{{ __('cms.product_reviews.review') }}</label>
                <textarea name="review" rows="4" class="form-control">{{ old('review', $review->review) }}</textarea>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="is_approved" value="1" class="form-check-input" id="is_approved" @checked(old('is_approved', $review->is_approved))>
                <label class="form-check-label" for="is_approved">{{ __('cms.product_reviews.approved') }}</label>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('cms.product_reviews.update') }}</button>
            <a href="{{ route('admin.reviews.index') }}" class="btn btn-secondary">{{ __('cms.product_reviews.back_button') }}</a>
        </form>
    </div>
</div>
@endsection
