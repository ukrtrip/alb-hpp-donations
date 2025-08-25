<?php

require __DIR__ . '/lib/src/SimpleJWT/InvalidTokenException.php';
require __DIR__ . '/lib/src/SimpleJWT/Token.php';
require __DIR__ . '/lib/src/SimpleJWT/JWT.php';
require __DIR__ . '/lib/src/SimpleJWT/JWE.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/CryptException.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/AlgorithmInterface.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/AlgorithmFactory.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/BaseAlgorithm.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Signature/SignatureAlgorithm.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Signature/SHA2.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Signature/EdDSA.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Signature/HMAC.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Signature/None.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Signature/OpenSSLSig.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/KeyManagementAlgorithm.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/KeyDerivationAlgorithm.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/KeyEncryptionAlgorithm.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/AESKeyWrapTrait.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/AESGCMKeyWrap.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/AESKeyWrap.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/DirectEncryption.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/ECDH.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/ECDH_AESKeyWrap.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/PBES2.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/KeyManagement/RSAES.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Encryption/EncryptionAlgorithm.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Encryption/AESGCM.php';
require __DIR__ . '/lib/src/SimpleJWT/Crypt/Encryption/AESCBC_HMACSHA2.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/KeyInterface.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/PEMInterface.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/ECDHKeyInterface.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/KeyException.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/Key.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/ECKey.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/KeyFactory.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/KeySet.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/OKPKey.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/RSAKey.php';
require __DIR__ . '/lib/src/SimpleJWT/Keys/SymmetricKey.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/BinaryEncodingException.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/Helper.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/Util.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/BigNum.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/CBOR/CBORException.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/CBOR/CBOR.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/CBOR/DataItem.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/ASN1/ASN1Exception.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/ASN1/DER.php';
require __DIR__ . '/lib/src/SimpleJWT/Util/ASN1/Value.php';

// Припускаю, що на даному етапі краще використовувати require
//spl_autoload_register(function ($class) {
//	$baseDir = __DIR__ . '/lib/src/SimpleJWT/';
//
//	$class = str_replace('SimpleJWT\\', '', $class);
//	$file = $baseDir . str_replace('\\', '/', $class) . '.php';
//
//	if (file_exists($file)) {
//		require $file;
//	}
//});
