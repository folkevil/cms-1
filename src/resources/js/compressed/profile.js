!function(a){function b(b){if("undefined"!=typeof b.html){a(".user-photo").replaceWith(b.html);var d=a(".user-photo>.current-photo").css("background-image").replace(/^url\(/,"").replace(/\)$/,"");a("#account-menu").find("img").attr("src",d),c()}}function c(){e.uploadButton=a(".user-photo-controls .upload-photo"),e.deleteButton=a(".user-photo-controls .delete-photo"),d=new Craft.ImageUpload(e)}var d=null,e={postParameters:{userId:a(".user-photo").attr("data-user")},modalClass:"profile-image-modal",uploadAction:"users/uploadUserPhoto",deleteMessage:Craft.t("Are you sure you want to delete this photo?"),deleteAction:"users/deleteUserPhoto",cropAction:"users/cropUserPhoto",areaToolOptions:{aspectRatio:"1:1",initialRectangle:{mode:"auto"}},onImageSave:function(a){b(a)},onImageDelete:function(a){b(a)}};a("input[name=userId]").val()&&c()}(jQuery);
//# sourceMappingURL=profile.js.map