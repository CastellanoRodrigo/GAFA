-- Ejecutar ANTES de usar el nuevo login: los hashes bcrypt miden 60 caracteres.
ALTER TABLE `usuarios` MODIFY `password` VARCHAR(255) NOT NULL;
-- Las contraseñas existentes en texto plano se convierten a hash automáticamente
-- en el primer inicio de sesión exitoso de cada usuario.
