function removeRow(id){
    $.ajax({
        url: '/tribune/delete',
        type: "post",
        data: {
            id: id
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response){ // What to do if we succeed
            alert("deleted succesfully");
            location.reload(); 
        },
        error: function(data){
            console.log('Error:', data);
        }
    });
}

$("#tribune").on('change',function(e){
    var fileName = e.target.files[0].name;
    $("#label").html(fileName);
});

