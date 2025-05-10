@extends('admin.layouts.app')

@section('content')
<div class="container">
    <h2>تعديل الحقل</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('categories.fields.update', [$category->id, $field->id]) }}" method="POST">
        @csrf
        <input type="hidden" name="_method" value="POST">

        <div class="mb-3">
            <label>اسم الحقل بالعربي</label>
            <input type="text" name="field_ar" class="form-control" value="{{ $field->field_ar }}" required>
        </div>

        <div class="mb-3">
            <label>اسم الحقل بالإنجليزي</label>
            <input type="text" name="field_en" class="form-control" value="{{ $field->field_en }}" required>
        </div>

        <h4>القيم:</h4>
        <div id="values-container">
      @foreach($field->values as $index => $value)
    <div class="value-group mb-2">
        <input type="hidden" name="value_ids[]" value="{{ $value->id }}">
        <input type="text" name="values_ar[]" class="form-control mb-2" value="{{ $value->value_ar }}" required placeholder="القيمة بالعربي">
        <input type="text" name="values_en[]" class="form-control mb-2" value="{{ $value->value_en }}" required placeholder="القيمة بالإنجليزي">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeValue(this)">حذف</button>
    </div>
@endforeach

        </div>

        <button type="button" class="btn btn-secondary" onclick="addValue()">إضافة قيمة جديدة</button>

        <br><br>
        <button type="submit" class="btn btn-success">حفظ التعديلات</button>
    </form>
</div>

<script>
    function addValue() {
        let container = document.getElementById('values-container');
        let div = document.createElement('div');
        div.className = 'value-group mb-2';
        div.innerHTML = `
            <input type="text" name="values_ar[]" class="form-control mb-2" required placeholder="القيمة بالعربي">
            <input type="text" name="values_en[]" class="form-control mb-2" required placeholder="القيمة بالإنجليزي">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeValue(this)">حذف</button>
        `;
        container.appendChild(div);
    }

    function removeValue(button) {
        button.parentElement.remove();
    }
</script>
@endsection
