document.addEventListener('DOMContentLoaded', function () {


    const citySelect = document.getElementById('trip_city');
    const placeSelect = document.getElementById('trip_place');
    const streetSelected = document.getElementById('street');
    const postalCodeSelected = document.getElementById('postalCode');
    const latitudeSelected = document.getElementById('latitude');
    const longitudeSelected = document.getElementById('longitude');

    const closeFormAddPlace = document.getElementById('btn-close-form');
    const modal = document.getElementById('modal-add-place');
    const formNewPlace = document.getElementById('form-nouveau-lieu');
    const placetitleElement = document.getElementById('placetitle');


    citySelect.addEventListener('change', function () {
        const cityId = citySelect.value;

        if (cityId) {
            const url = apiCityPlacesUrl.replace('0', cityId);
            fetch(url)
        .then(response => response.json())
                .then(data => {
                    placeSelect.innerHTML = '<option value="">Choisissez un lieu</option>';

                    data.forEach(place => {
                        const option = document.createElement('option');
                        option.value = place.id;
                        option.textContent = place.name ;
                        placeSelect.appendChild(option);
                    });
                    const option = document.createElement('option');
                    option.textContent ='Ajoutez Lieu';
                    placeSelect.appendChild(option);
                })
                .catch(error => console.error('Error fetching places:', error));
        } else {
            placeSelect.innerHTML = '<option value="">Choisissez un lieu</option>';
        }
    });


    placeSelect.addEventListener('change', function () {
        const placeId = placeSelect.value;
        if (placeId ==='Ajoutez Lieu'){
            displayFormAddPlace();
        }
        else if(placeId) {
            url = apiPlaceInfoUrl.replace('0', placeId);
            fetch(url)
                .then(response => response.json())
                .then(data=> {
                    const placeData = data[0];
                    streetSelected.value = placeData.street;
                    postalCodeSelected.value = placeData.postalCode;
                    latitudeSelected.value = placeData.latitude;
                    longitudeSelected.value = placeData.longitude;
                })
        }
    })

    closeFormAddPlace.addEventListener('click',  () => {modal.hidden = true;
        document.querySelectorAll('.hide-on-modal').forEach(element => {
            element.style.display = "block";
        });
    });
    formNewPlace.addEventListener('click', sendPlaceForm);

    function displayFormAddPlace(){
        let userCityChoice = citySelect.options[citySelect.value].textContent;
        let titleText = 'Nouveau lieu pour '+ userCityChoice;
        placetitleElement.innerText = titleText;
        modal.hidden = !modal.hidden;
        document.querySelectorAll('.hide-on-modal').forEach(element => {
            element.style.display = "none";
        });
    }

    // Soumettre le formulaire en AJAX pour ajouter un nouveau lieu
    function sendPlaceForm (e) {
        e.preventDefault();
        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');


        formData.append('csrf_token', csrfToken);
        formData.append('name', document.getElementById('place_name').value);
        formData.append('street', document.getElementById('place_street').value);
        formData.append('latitude', document.getElementById('place_latitude').value);
        formData.append('longitude', document.getElementById('place_longitude').value);
        formData.append('cityId', citySelect.options[citySelect.value].value);

        fetch('/place/place/create', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ajouter le nouveau lieu en avant dernier du select
                    let newOption = new Option(data.place.name, data.place.id, true, true);
                    let beforeLastIndex = placeSelect.options.length - 1;
                    placeSelect.add(newOption, beforeLastIndex);

                    //Remplir les autres champs avec les informations du nouveau lieu
                    streetSelected.value = data.place.street;
                    postalCodeSelected.value = data.place.postalCode;
                    latitudeSelected.value = data.place.latitude;
                    longitudeSelected.value = data.place.longitude;

                    // Fermer la modale et réinitialiser le formulaire
                    modal.hidden = !modal.hidden;

                    formNewPlace.reset();
                } else {
                    alert('Erreur lors de l\'ajout du lieu');
                }
            })
            .catch(error => console.error('Erreur lors de l\'ajout du lieu:', error));
    }
});