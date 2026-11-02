$(function () {

    $(document).on('click', '#doc_sign', function () {
//        console.log($(this).is(':checked'));
        if ($(this).is(':checked')) {
//            console.log('yes');
            Swal.fire({
                title: "Do you want to save this as signed?",
                showCancelButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `Cancel`
            }).then((result) => {
                if (result.isConfirmed) {

                    $.post("/?action=document_sign_submit", {
                        note_id: $('#note_id').val(),
                        read_sign: '1',
                    }, function (x) {
                        Swal.fire({
                            position: "top-end",
                            icon: "success",
                            title: "Thank you",
                            showConfirmButton: false,
                            timer: 1500
                        });
                        setTimeout(function () {
                            location.href = '/user/publication/get_publications';
                        }, 1500);
                    }).fail();


                } else {
                    $('#doc_sign').prop('checked', false);
                }
            });
        } else {
//            console.log('no');
            Swal.fire({
                title: "Do you want to save this as not signed?",
                showCancelButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `Cancel`
            }).then((result) => {
                if (result.isConfirmed) {

                    $.post("/?action=document_sign_submit", {
                        note_id: $('#note_id').val(),
                        read_sign: '0',
                    }, function (x) {
                        Swal.fire({
                            position: "top-end",
                            icon: "success",
                            title: "Thank you",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }).fail();
                } else {
                    $('#doc_sign').prop('checked', true);
                }
            });
        }

    });



//    IWI identify what was change in version start

    $('#showHistory').click(function () {
        $('.historySec').slideToggle("fast");
    });






    highlightUpdatedContent();


    function highlightUpdatedContent() {

        var totalHistoryCount = parseInt($('#totalHistoryCount').val());

//        console.log(totalHistoryCount);

        for (let th = 0; th < totalHistoryCount; th++) {

//        var oldBody = $(".old_body_text").text().trim();
//        var newBody = $(".body_text").text().trim();


            var oldBody = $("#hisCon_" + th + " .old_val").text().trim();
            var newBody = $("#hisCon_" + th + " .new_val").text().trim();

            var oldBody_split = oldBody.split(' ');
            var newBody_split = newBody.split(' ');

            if (oldBody != '') {

//            console.log(oldBody);
//            console.log(newBody);
//
//            console.log(oldBody_split);
//            console.log(newBody_split);

                algoReturn = sentenceChangeAlgo(oldBody, newBody)

//            console.log(algoReturn);
                var myWords = [];
                var myOutputHTML = "";

                for (let i = 0; i < newBody_split.length; i++) {
                    // Init Word object with empty properties
                    var myWord = new Object();
                    myWord.value = "";
                    myWord.html = "";
                    myWord.newWord = false;
                    myWord.delWord = false;

                    var currentWord = newBody_split[i]
                    myWord.value = currentWord
                    if (algoReturn.newWords.includes(currentWord)) {
                        myWord.html = "<span class='new'>" + currentWord + "</span>"
                        myWord.newWord = true
                    } else {
                        myWord.html = currentWord;
                    }
                    myWords.push(myWord);
                }
                for (let i = 0; i < algoReturn.delWordsIndices.length; i++) {
                    var myWord = new Object()
                    myWord.value = algoReturn.delWords[i]
                    myWord.html = "<span class='del'>" + algoReturn.delWords[i] + "</span>"
                    myWord.delWord = true
                    myWords.splice(algoReturn.delWordsIndices[i], 0, myWord)

                }

//            console.log(myWords);

                myWords.map((item) => {
                    myOutputHTML += item.html + " ";
                })
                $("#hisCon_" + th + " .output_val").html(myOutputHTML);

            }
        }



    }


    function sentenceChangeAlgo(string1, string2) {
        // Tokenization based on space only
        var string1Splitted = string1.split(" ")
        var string2Splitted = string2.split(" ")

        // Variables to save settings
        var newWords = []
        var newWordsIndices = []

        var delWords = []
        var delWordsIndices = []

        var shiftedWords = []
        var shiftedWordsIndices = []

        // Check for new words
        // Loop through the first string
        for (let i = 0; i < string2Splitted.length; i++) {
            var currentWord = string2Splitted[i]
            var isFound = false
            // Loop through the second string
            for (let j = 0; j < string1Splitted.length; j++) {
                // Word found
                if (currentWord == string1Splitted[j]) {
                    isFound = true
                    break
                }
            }
            // Word not found
            if (isFound == false)
                newWords.push(currentWord)
        }

        // Check for deleted words 
        // Loop through the first string
        for (let i = 0; i < string1Splitted.length; i++) {
            var currentWord = string1Splitted[i]
            var isFound = false
            // Loop through the second string
            for (let j = 0; j < string2Splitted.length; j++) {
                // Word found
                if (currentWord == string2Splitted[j]) {
                    isFound = true
                    break
                }
            }
            // Word not found
            if (isFound == false)
                delWords.push(currentWord)
        }


        // Check for shifted words
        // Loop through the first string
        for (let i = 0; i < string1Splitted.length; i++) {
            var currentWord = string1Splitted[i]


            // Loop through the second string
            for (let j = 0; j < string2Splitted.length; j++) {
                // Word found
                if (currentWord == string2Splitted[j]) {
                    if (i != j) {
                        shiftedWords.push(currentWord)
                        shiftedWordsIndices.push(j)
                    }
                    break
                }
            }

        }

        // Detect new words indices
        for (let i = 0; i < newWords.length; i++) {
            // Loop through the second string
            for (let j = 0; j < string2Splitted.length; j++) {
                // Word found
                if (newWords[i] == string2Splitted[j]) {
                    newWordsIndices.push(j)
                    break
                }
            }
        }

        // Detect deleted words indices 
        for (let i = 0; i < delWords.length; i++) {
            // Loop through the second string
            for (let j = 0; j < string1Splitted.length; j++) {
                // Word found
                if (delWords[i] == string1Splitted[j]) {
                    delWordsIndices.push(j)
                    break
                }
            }
        }

        // console.log("new words:", newWords)
        // console.log("new words indices:", newWordsIndices)
        // console.log("deleted words:", delWords)
        // console.log("deleted words indices:", delWordsIndices)
        // console.log("shifted words:", shiftedWords)
        // console.log("shifted words indices", shiftedWordsIndices)

        var algoReturn = new Object()
        algoReturn.newWords = newWords
        algoReturn.delWords = delWords
        algoReturn.shiftedWords = shiftedWords
        algoReturn.shiftedWordsIndices = shiftedWordsIndices
        algoReturn.newWordsIndices = newWordsIndices
        algoReturn.delWordsIndices = delWordsIndices
        return algoReturn
    }




//    IWI identify what was change in version end


});