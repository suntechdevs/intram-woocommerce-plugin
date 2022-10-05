window.addEventListener("load", function () {
    const button = document.querySelector("#btn-intram-open-widget");
    console.log("data",data);

    button.addEventListener('click', function (event) {
        event.preventDefault();
        intramOpenWidget.init({
            public_key:data.public_key, //your public api key
            amount:data.response.total_amount, //replace your product price
            sandbox:data.sandbox, // choose the right public key : sandbox=test or live (see your intram account)
            currency:'xof', //choose the currency
            company:{
                name:data.nameStore, //your company namr
                template:'default+', // payment gate template
                color:data.color, // payment gate template color
                logo_url:data.urlLogo // your company logo
            },
        }).then((data)=>{
            console.log(data,'****** responses')
        });
    });
});
