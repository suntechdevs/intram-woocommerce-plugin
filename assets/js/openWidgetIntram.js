window.addEventListener("load", function () {
    const button = document.querySelector("#btn-intram-open-widget");
    console.log("data",data);
   /* inputs.testmode === "yes" ? inputs.test = "sandbox" : ""
*/
    button.addEventListener('click', function (event) {
        event.preventDefault();
       // inputs.sdk = "woocommerce";
       // alert("dd");
        window.intramOpenWidget(
            {
                url:data.url,
                key:data.key
            }
        )
    });
});
