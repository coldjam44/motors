<?php $__env->startSection('content'); ?>
<div class="container">
    <h2>تعديل الحقل</h2>

    <?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <form action="<?php echo e(route('categories.fields.update', [$category->id, $field->id])); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="_method" value="POST">

        <div class="mb-3">
            <label>اسم الحقل بالعربي</label>
            <input type="text" name="field_ar" class="form-control" value="<?php echo e($field->field_ar); ?>" required>
        </div>

        <div class="mb-3">
            <label>اسم الحقل بالإنجليزي</label>
            <input type="text" name="field_en" class="form-control" value="<?php echo e($field->field_en); ?>" required>
        </div>

        <h4>القيم:</h4>
        <div id="values-container">
            <?php $__currentLoopData = $field->values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="value-group mb-2">
                    <input type="text" name="values_ar[]" class="form-control mb-2" value="<?php echo e($value->value_ar); ?>" required placeholder="القيمة بالعربي">
                    <input type="text" name="values_en[]" class="form-control mb-2" value="<?php echo e($value->value_en); ?>" required placeholder="القيمة بالإنجليزي">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeValue(this)">حذف</button>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/azsystems-motors/htdocs/motors.azsystems.tech/public/resources/views/categories/fields/edit.blade.php ENDPATH**/ ?>