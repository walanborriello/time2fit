import React from 'react';
import './T2FTextarea.css';

const T2FTextarea = ({ label, error, ...props }) => {
  return (
    <div className="t2f-input-wrapper">
      {label && <label className="t2f-input-label">{label}</label>}
      <textarea className={`t2f-textarea ${error ? 't2f-textarea--error' : ''}`} {...props} />
      {error && <span className="t2f-input-error">{error}</span>}
    </div>
  );
};

export default T2FTextarea;


