const fs = require('fs');
const path = require('path');

module.exports = (req, res) => {
  const authorization = req.headers.authorization || '';
  const expectedUser = process.env.ADMIN_USER;
  const expectedPassword = process.env.ADMIN_PASSWORD;

  if (!expectedUser || !expectedPassword || !authorization.startsWith('Basic ')) {
    return unauthorized(res);
  }

  const decoded = Buffer.from(authorization.slice(6), 'base64').toString('utf8');
  const separator = decoded.indexOf(':');
  const user = separator >= 0 ? decoded.slice(0, separator) : '';
  const password = separator >= 0 ? decoded.slice(separator + 1) : '';

  if (user !== expectedUser || password !== expectedPassword) {
    return unauthorized(res);
  }

  res.setHeader('Content-Type', 'text/html; charset=utf-8');
  res.status(200).send(fs.readFileSync(path.join(process.cwd(), 'index.html')));
};

function unauthorized(res) {
  res.setHeader('WWW-Authenticate', 'Basic realm="Owner management"');
  return res.status(401).send('Owner access required.');
}
