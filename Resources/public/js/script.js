/**
 * Novo SGA - Monitor
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
var App = App || {};

App.Monitor = {

    ids: [],
    labelTransferir: '',
    alertCancelar: '',
    alertReativar: '',
    timeoutId: 0,
    
    Senha: {

        dialogView: '#dialog-view',
        dialogSearch: '#dialog-busca',
        dialogTransfere: '#dialog-transfere',
        
    }

};


(function () {
    'use strict'
    
    var app = new Vue({
        el: '#monitor',
        data: {
            search: '',
            searchResult: [],
            servicos: [],
            atendimento: null,
            novoServico: '',
            novaPrioridade: ''
        },
        methods: {
            ajaxUpdate: function() {
                var self = this;
                clearTimeout(App.Monitor.timeoutId);
                if (!App.paused) {
                    App.ajax({
                        url: App.url('/novosga.monitor/ajax_update'),
                        data: {
                            ids: ids.join(',')
                        },
                        success: function(response) {
                            self.servicos = response.data;
                        },
                        complete: function() {
                            App.Monitor.timeoutId = setTimeout(self.ajaxUpdate, App.updateInterval);
                        }
                    });
                } else {
                    App.Monitor.timeoutId = setTimeout(self.ajaxUpdate, App.updateInterval);
                }
            },
            
            /**
             * Busca informacoes do atendimento pelo id.
             */
            view: function(atendimento) {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.monitor/info_senha/') + atendimento.id,
                    success: function(response) {
                        self.atendimento = response.data;
                        $('#dialog-view').modal('show');
                    }
                });
            },

            consulta: function() {
                $('#dialog-busca').modal('show');
                this.consultar();
            },

            consultar: function() {
                var self = this;
                
                App.ajax({
                    url: App.url('/novosga.monitor/buscar'),
                    data: {
                        numero: self.search
                    },
                    success: function(response) {
                        self.searchResult = response.data;
                    }
                });
            },

            transfere: function(atendimento) {
                this.atendimento = atendimento;
                $('#dialog-transfere').modal('show');
            },

            transferir: function(atendimento, novoServico, novaPrioridade) {
                App.ajax({
                    url: App.url('/novosga.monitor/transferir/') + atendimento.id,
                    type: 'post',
                    data: {
                        servico: novoServico,
                        prioridade: novaPrioridade
                    },
                    success: function() {
                        $('.modal').modal('hide');
                    }
                });
            },

            reativar: function(atendimento) {
                if (window.confirm(App.Monitor.alertReativar)) {
                    App.ajax({
                        url: App.url('/novosga.monitor/reativar/') + atendimento.id,
                        type: 'post',
                        success: function() {
                            $('.modal').modal('hide');
                        }
                    });
                }
            },

            cancelar: function(atendimento) {
                if (window.confirm(App.Monitor.alertCancelar)) {
                    App.ajax({
                        url: App.url('/novosga.monitor/cancelar/') + atendimento.id,
                        type: 'post',
                        success: function() {
                            $('.modal').modal('hide');
                        }
                    });
                }
            },
            
            init: function() {
                this.ajaxUpdate();
            }
        },
    });
    
    app.init();
})();