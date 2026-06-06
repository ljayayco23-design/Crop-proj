<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editUserForm" action="#">
                @csrf
                <input type="hidden" name="id" id="eid">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="ename" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" id="eemail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="estatus" class="form-select">
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="declined">Declined</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    window.editUser = function(id, name, email, status) {
        document.getElementById('eid').value = id;
        document.getElementById('ename').value = name;
        document.getElementById('eemail').value = email;
        document.getElementById('estatus').value = status || 'pending';
        
        document.getElementById('editUserForm').action = "{{ url('admin/users') }}/" + id + "/update";
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    };
});
</script>